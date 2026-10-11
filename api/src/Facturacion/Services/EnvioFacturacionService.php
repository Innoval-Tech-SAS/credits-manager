<?php

namespace App\Facturacion\Services;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\EstadoEnvioCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Jobs\EnviarDocumentoFacturacionCorreoJob;
use App\Correo\Mail\CorreoDocumentoFacturacion;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Models\PlantillaCorreo;
use App\Correo\Repositories\ConfiguracionCorreoRepository;
use App\Correo\Repositories\PlantillaCorreoRepository;
use App\Correo\Services\FabricaMailerCobranza;
use App\Correo\Services\PlantillaCorreoService;
use App\Empresas\Repositories\EmpresaRepository;
use App\Facturacion\Enums\EstadoDocumentoFacturacion;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Models\DocumentoFacturacion;
use App\Facturacion\Models\EnvioFacturacion;
use App\Facturacion\Repositories\DocumentoFacturacionRepository;
use App\Facturacion\Repositories\EnvioFacturacionRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Throwable;

class EnvioFacturacionService
{
    public function __construct(
        protected EnvioFacturacionRepository $envios,
        protected DocumentoFacturacionRepository $documentosRepositorio,
        protected ConfiguracionCorreoRepository $configuraciones,
        protected PlantillaCorreoRepository $plantillas,
        protected PlantillaCorreoService $renderizador,
        protected FabricaMailerCobranza $fabrica,
        protected DocumentoFacturacionService $documentos,
        protected EmpresaRepository $empresas,
    ) {}

    /**
     * Ya no genera el PDF aquí ni lo manda con el job: el job lo regenera él mismo a partir del `documento_facturacion_id`
     * (sin sesión, con `empresa_id` explícito). Antes el PDF viajaba como argumento binario del job y rompía la
     * serialización JSON del payload de la cola Redis (bytes no-UTF8) — el envío quedaba atascado en `en_cola` para
     * siempre porque el job nunca llegaba a encolarse.
     */
    public function solicitar(Usuario $usuario, DocumentoFacturacion $documento): EnvioFacturacion
    {
        $empresaId = (int) $usuario->empresa_id;
        $configuracion = $this->configuraciones->deEmpresaYTipo($empresaId, TipoCorreo::Facturacion);
        $plantilla = $this->plantillas->predeterminadaDeEmpresa($empresaId, TipoCorreo::Facturacion);

        $this->validarListo($configuracion, $plantilla, $documento);

        $valores = $this->renderizador->valoresDeFactura($this->nombreEmpresa($empresaId), $documento, $this->documentos->totales($documento)['total']);

        $envio = $this->envios->registrar($empresaId, [
            'documento_facturacion_id' => $documento->id,
            'plantilla_correo_id' => $plantilla->id,
            'enviado_por' => $usuario->id,
            'destinatario' => $documento->cliente->tercero->correo,
            'asunto' => $this->renderizador->renderizarAsunto($plantilla->asunto, $valores),
            'cuerpo' => $this->renderizador->renderizarCuerpo($plantilla->cuerpo, $valores),
            'estado' => EstadoEnvioCorreo::EnCola,
        ]);

        EnviarDocumentoFacturacionCorreoJob::dispatch($envio->id, $empresaId);

        return $envio;
    }

    /** Lo ejecuta el job, sin sesión: documento y empresa llegan por id explícito. El PDF se regenera aquí, no viaja por la cola. */
    public function ejecutar(int $envioId, int $empresaId): void
    {
        $envio = $this->envios->deEmpresa($envioId, $empresaId);

        if ($envio === null || $envio->estado !== EstadoEnvioCorreo::EnCola) {
            return;
        }

        $configuracion = $this->configuraciones->deEmpresaYTipo($empresaId, TipoCorreo::Facturacion);

        try {
            if ($configuracion === null) {
                throw new RuntimeException('La empresa no tiene configuración de correo.');
            }

            $documento = $this->documentosRepositorio->deEmpresa($envio->documento_facturacion_id, $empresaId);

            if ($documento === null) {
                throw new RuntimeException('El documento de facturación ya no existe.');
            }

            $empresa = $this->empresas->buscarPorIdOFallar($empresaId);
            $pdf = $this->documentos->generarPdfInterno($documento, $empresa);

            $this->fabrica->paraConfiguracion($configuracion)->send(new CorreoDocumentoFacturacion(
                asunto: $envio->asunto,
                cuerpoHtml: (string) $envio->cuerpo,
                destinatario: $envio->destinatario,
                remitente: $configuracion->remitente,
                nombreRemitente: $configuracion->nombre_remitente,
                pdfContenido: $pdf->contenido,
                pdfNombreArchivo: $pdf->nombreArchivo,
            ));

            $envio->estado = EstadoEnvioCorreo::Enviado;
            $envio->enviado_en = now();
            $envio->detalle_error = null;
        } catch (Throwable $excepcion) {
            Log::warning('Envío de documento de facturación fallido', ['envio_id' => $envioId, 'empresa_id' => $empresaId, 'excepcion' => $excepcion::class]);

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

    /** Reintenta un envío fallido: revalida que el documento siga emitido (no anulado) antes de reencolar. */
    public function reintentar(Usuario $usuario, DocumentoFacturacion $documento, int $envioId): EnvioFacturacion
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($empresaId, $documento, $envioId) {
            $envio = $this->envios->deEmpresa($envioId, $empresaId);

            if ($envio === null || $envio->documento_facturacion_id !== $documento->id) {
                abort(404);
            }

            if ($envio->estado !== EstadoEnvioCorreo::Fallido) {
                throw ValidationException::withMessages(['envio' => __('validation.custom.correo.reintento_no_aplica')]);
            }

            $this->exigirAunVigente($documento);

            $envio->estado = EstadoEnvioCorreo::EnCola;
            $envio->detalle_error = null;
            $envio->save();

            EnviarDocumentoFacturacionCorreoJob::dispatch($envio->id, $empresaId);

            return $envio;
        });
    }

    /** @return array{envios: list<array<string, mixed>>} */
    public function historial(DocumentoFacturacion $documento): array
    {
        return [
            'envios' => $this->envios->deDocumento($documento->id)
                ->map(fn (EnvioFacturacion $envio) => $this->representar($envio))
                ->all(),
        ];
    }

    public function representar(EnvioFacturacion $envio): array
    {
        return [
            'id' => $envio->id,
            'destinatario' => $envio->destinatario,
            'asunto' => $envio->asunto,
            'estado' => $envio->estado->value,
            'detalle_error' => $envio->detalle_error,
            'enviado_en' => $envio->enviado_en?->toIso8601String(),
            'creado_en' => $envio->creado_en?->toIso8601String(),
        ];
    }

    private function exigirAunVigente(DocumentoFacturacion $documento): void
    {
        if ($documento->estado !== EstadoDocumentoFacturacion::Emitido) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.ya_anulada')]);
        }

        if ($documento->tipo === TipoDocumentoFacturacion::Factura) {
            $totales = $this->documentos->totales($documento);

            if ($totales['anulada'] ?? false) {
                throw ValidationException::withMessages(['documento' => __('validation.custom.facturacion.ya_anulada')]);
            }
        }
    }

    private function validarListo(?ConfiguracionCorreo $configuracion, ?PlantillaCorreo $plantilla, DocumentoFacturacion $documento): void
    {
        if ($configuracion === null) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.correo.sin_configurar')]);
        }

        if ($plantilla === null) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.correo.sin_plantilla')]);
        }

        $documento->loadMissing('cliente.tercero');

        if (blank($documento->cliente->tercero->correo)) {
            throw ValidationException::withMessages(['documento' => __('validation.custom.correo.cliente_sin_correo')]);
        }
    }

    private function nombreEmpresa(int $empresaId): string
    {
        return $this->empresas->buscarPorIdOFallar($empresaId)->nombre_empresa;
    }
}
