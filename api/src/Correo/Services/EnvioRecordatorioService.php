<?php

namespace App\Correo\Services;

use App\Clientes\Models\Cliente;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Jobs\EnviarRecordatorioCobranzaJob;
use App\Correo\Mail\CorreoCobranza;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Models\EnvioCorreo;
use App\Correo\Models\PlantillaCorreo;
use App\Correo\Repositories\ConfiguracionCorreoRepository;
use App\Correo\Repositories\EnvioCorreoRepository;
use App\Correo\Repositories\PlantillaCorreoRepository;
use App\Correo\Support\ContextoFalloCorreo;
use App\Creditos\Enums\EstadoCuota;
use App\Creditos\Models\Cuota;
use App\Creditos\Repositories\CuotaRepository;
use App\Empresas\Repositories\EmpresaRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\DB;
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
        protected PlantillaCorreoRepository $plantillas,
        protected PlantillaCorreoService $renderizador,
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

    /** Vista previa con la plantilla elegida (o la predeterminada, si no se indica ninguna). */
    public function vistaPrevia(Usuario $usuario, Cliente $cliente, int $cuotaId, ?int $plantillaId): array
    {
        $empresaId = (int) $usuario->empresa_id;
        $cuota = $this->cuotaCobrable($cliente, $cuotaId);
        $plantilla = $this->resolverPlantilla($empresaId, $plantillaId);

        $valores = $this->renderizador->valoresDeCuota($this->nombreEmpresa($usuario), $cliente, $cuota);

        return [
            'asunto' => $this->renderizador->renderizarAsunto($plantilla->asunto, $valores),
            'cuerpo' => $this->renderizador->renderizarCuerpo($plantilla->cuerpo, $valores),
            'destinatario' => $cliente->tercero->correo,
        ];
    }

    /** Congela el mensaje renderizado en `envios_correo` y lo encola. El envío real lo hace el job. */
    public function solicitar(Usuario $usuario, Cliente $cliente, int $cuotaId, ?int $plantillaId): EnvioCorreo
    {
        $empresaId = (int) $usuario->empresa_id;
        $configuracion = $this->configuraciones->deEmpresaYTipo($empresaId, TipoCorreo::Cobranza);
        $cuota = $this->cuotaCobrable($cliente, $cuotaId);
        $plantilla = $this->resolverPlantilla($empresaId, $plantillaId);

        $this->validarListo($configuracion, $cliente);

        $valores = $this->renderizador->valoresDeCuota($this->nombreEmpresa($usuario), $cliente, $cuota);

        $envio = $this->envios->registrar($empresaId, [
            'cliente_id' => $cliente->id,
            'cuota_id' => $cuota->id,
            'plantilla_correo_id' => $plantilla->id,
            'enviado_por' => $usuario->id,
            'destinatario' => $cliente->tercero->correo,
            'asunto' => $this->renderizador->renderizarAsunto($plantilla->asunto, $valores),
            'cuerpo' => $this->renderizador->renderizarCuerpo($plantilla->cuerpo, $valores),
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

        $configuracion = $this->configuraciones->deEmpresaYTipo($empresaId, TipoCorreo::Cobranza);
        $inicio = hrtime(true);

        try {
            if ($configuracion === null || blank($configuracion->host)) {
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
            Log::warning('Envío de recordatorio de cobranza fallido', [
                'envio_id' => $envioId,
                'empresa_id' => $empresaId,
                ...ContextoFalloCorreo::construir($configuracion, $excepcion, (hrtime(true) - $inicio) / 1_000_000),
            ]);

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

    /**
     * Reintenta un envío fallido: revalida que la cuota siga sin pagar (si se pagó mientras tanto, el reintento no
     * tiene sentido) y vuelve a encolar el mismo job. `empresa_id` explícito porque lo usa un contexto autenticado,
     * pero el envío puede ser viejo: no asumir que sigue siendo vigente sin revisar.
     */
    public function reintentar(Usuario $usuario, Cliente $cliente, int $envioId): EnvioCorreo
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($empresaId, $cliente, $envioId) {
            $envio = $this->envios->deEmpresa($envioId, $empresaId);

            if ($envio === null || $envio->cliente_id !== $cliente->id) {
                abort(404);
            }

            if ($envio->estado !== EstadoEnvioCorreo::Fallido) {
                throw ValidationException::withMessages(['envio' => __('validation.custom.correo.reintento_no_aplica')]);
            }

            if ($envio->cuota_id !== null) {
                $this->cuotaCobrable($cliente, $envio->cuota_id);
            }

            $envio->estado = EstadoEnvioCorreo::EnCola;
            $envio->detalle_error = null;
            $envio->save();

            EnviarRecordatorioCobranzaJob::dispatch($envio->id, $empresaId);

            return $envio;
        });
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

    private function resolverPlantilla(int $empresaId, ?int $plantillaId): PlantillaCorreo
    {
        $plantilla = $plantillaId !== null
            ? $this->plantillas->deEmpresa($plantillaId, $empresaId)
            : $this->plantillas->predeterminadaDeEmpresa($empresaId, TipoCorreo::Cobranza);

        if ($plantilla === null) {
            throw ValidationException::withMessages(['cuota_id' => __('validation.custom.correo.sin_plantilla')]);
        }

        return $plantilla;
    }

    private function validarListo(?ConfiguracionCorreo $configuracion, Cliente $cliente): void
    {
        if ($configuracion === null || blank($configuracion->host)) {
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
