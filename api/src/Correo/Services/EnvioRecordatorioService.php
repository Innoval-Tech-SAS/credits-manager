<?php

namespace App\Correo\Services;

use App\Clientes\Models\Cliente;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Jobs\EnviarRecordatorioCobranzaJob;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Models\EnvioCorreo;
use App\Correo\Repositories\ConfiguracionCorreoRepository;
use App\Correo\Repositories\EnvioCorreoRepository;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CuotaRepository;
use App\Empresas\Repositories\EmpresaRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EnvioRecordatorioService
{
    public function __construct(
        protected CuotaRepository $cuotas,
        protected ConfiguracionCorreoRepository $configuraciones,
        protected EnvioCorreoRepository $envios,
        protected PlantillaCorreoService $plantillas,
        protected FabricaMailerCobranza $fabrica,
        protected EmpresaRepository $empresas,
    ) {}

    /** Historial del cliente y cuotas que se pueden cobrar, para el diálogo de la ficha. */
    public function historial(Cliente $cliente): array
    {
        return [
            'envios' => $this->envios->historialDeCliente($cliente->id)
                ->map(fn (EnvioCorreo $envio) => $this->representar($envio))
                ->all(),
            'cuotas_cobrables' => $this->cuotas->cobrablesDelCliente($cliente->id)
                ->map(fn (Cuota $cuota) => [
                    'id' => $cuota->id,
                    'numero_cuota' => $cuota->numero_cuota,
                    'credito_codigo' => $cuota->credito->codigo,
                    'valor' => $cuota->valor->aDecimalString(),
                    'fecha_vencimiento' => $cuota->fecha_vencimiento->format('Y-m-d'),
                    'estado' => $cuota->estado->value,
                ])
                ->all(),
        ];
    }

    /** Vista previa de un cliente real, con la plantilla que llega (aún sin guardar). */
    public function vistaPrevia(Usuario $usuario, Cliente $cliente, int $cuotaId, ?string $asunto, ?string $cuerpo): array
    {
        $cuota = $this->cuotaCobrable($cliente, $cuotaId);
        $configuracion = $this->configuraciones->deEmpresa((int) $usuario->empresa_id);
        $asunto ??= $configuracion?->plantilla_asunto;
        $cuerpo ??= $configuracion?->plantilla_cuerpo;

        if ($asunto === null || $cuerpo === null) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.sin_configurar')]);
        }

        $valores = $this->plantillas->valoresDeCuota($this->nombreEmpresa($usuario), $cliente, $cuota);

        return [
            'asunto' => $this->plantillas->renderizarAsunto($asunto, $valores),
            'cuerpo' => $this->plantillas->renderizarCuerpo($this->plantillas->sanitizarHtml($cuerpo), $valores),
            'destinatario' => $cliente->tercero->correo,
        ];
    }

    /** Congela el mensaje renderizado en `envios_correo` y lo encola. El envío real lo hace el job. */
    public function solicitar(Usuario $usuario, Cliente $cliente, int $cuotaId): EnvioCorreo
    {
        $empresaId = (int) $usuario->empresa_id;
        $configuracion = $this->configuraciones->deEmpresa($empresaId);
        $cuota = $this->cuotaCobrable($cliente, $cuotaId);

        $this->validarListo($configuracion, $cliente);

        $valores = $this->plantillas->valoresDeCuota($this->nombreEmpresa($usuario), $cliente, $cuota);

        $envio = $this->envios->registrar($empresaId, [
            'cliente_id' => $cliente->id,
            'cuota_id' => $cuota->id,
            'enviado_por' => $usuario->id,
            'plantilla' => CorreoConstants::PLANTILLA_RECORDATORIO,
            'destinatario' => $cliente->tercero->correo,
            'asunto' => $this->plantillas->renderizarAsunto($configuracion->plantilla_asunto, $valores),
            'cuerpo' => $this->plantillas->renderizarCuerpo($configuracion->plantilla_cuerpo, $valores),
            'estado' => EstadoEnvioCorreo::EnCola,
        ]);

        EnviarRecordatorioCobranzaJob::dispatch($envio->id, $empresaId);

        return $envio;
    }

    /** Lo ejecuta el job, sin sesión: todo llega por id y empresa explícitos. Idempotente: solo actúa sobre envíos en cola. */
    public function ejecutar(int $envioId, int $empresaId): void
    {
        $envio = $this->envios->deEmpresa($envioId, $empresaId);

        if ($envio === null || $envio->estado !== EstadoEnvioCorreo::EnCola) {
            return;
        }

        $configuracion = $this->configuraciones->deEmpresa($empresaId);

        try {
            if ($configuracion === null) {
                throw new RuntimeException('La empresa no tiene configuración de correo.');
            }

            $this->fabrica->paraConfiguracion($configuracion)->send(new CorreoCobranza(
                asunto: $envio->asunto,
                cuerpoHtml: $envio->cuerpo,
                destinatario: $envio->destinatario,
                remitente: $configuracion->remitente,
                nombreRemitente: $configuracion->nombre_remitente,
            ));

            $envio->estado = EstadoEnvioCorreo::Enviado;
            $envio->enviado_en = now();
            $envio->detalle_error = null;
        } catch (Throwable $excepcion) {
            Log::warning('Envío de recordatorio de cobranza fallido', ['envio_id' => $envioId, 'empresa_id' => $empresaId, 'excepcion' => $excepcion::class]);

            $envio->estado = EstadoEnvioCorreo::Fallido;
            $envio->detalle_error = $excepcion instanceof TransportExceptionInterface
                ? CorreoConstants::MENSAJE_SMTP_FALLIDO
                : CorreoConstants::MENSAJE_ENVIO_FALLIDO;
        }

        $envio->save();
    }

    /** Red de seguridad del job: un envío que murió en cola no puede quedar "en cola" para siempre. */
    public function marcarFallido(int $envioId, int $empresaId): void
    {
        $envio = $this->envios->deEmpresa($envioId, $empresaId);

        if ($envio === null || $envio->estado !== EstadoEnvioCorreo::EnCola) {
            return;
        }

        $envio->estado = EstadoEnvioCorreo::Fallido;
        $envio->detalle_error = CorreoConstants::MENSAJE_ENVIO_FALLIDO;
        $envio->save();
    }

    /** Sin cliente de otra empresa ni de otro asesor: la relación cliente→crédito→cuota pasa por los scopes. */
    private function cuotaCobrable(Cliente $cliente, int $cuotaId): Cuota
    {
        $cuota = $this->cuotas->delClienteConCredito($cuotaId, $cliente->id);

        if ($cuota === null) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.cuota_no_del_cliente')]);
        }

        if ($cuota->estado === EstadoCuota::Pagada) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.cuota_pagada')]);
        }

        return $cuota;
    }

    private function validarListo(?ConfiguracionCorreo $configuracion, Cliente $cliente): void
    {
        if ($configuracion === null) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.sin_configurar')]);
        }

        $cliente->loadMissing('tercero');

        if (blank($cliente->tercero->correo)) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.cliente_sin_correo')]);
        }
    }

    private function nombreEmpresa(Usuario $usuario): string
    {
        return $this->empresas->buscarPorIdOFallar($usuario->empresa_id)->nombre_empresa;
    }

    public function representar(EnvioCorreo $envio): array
    {
        return [
            'id' => $envio->id,
            'cuota_id' => $envio->cuota_id,
            'destinatario' => $envio->destinatario,
            'asunto' => $envio->asunto,
            'estado' => $envio->estado->value,
            'detalle_error' => $envio->detalle_error,
            'enviado_en' => $envio->enviado_en?->toIso8601String(),
            'creado_en' => $envio->creado_en?->toIso8601String(),
        ];
    }
}
