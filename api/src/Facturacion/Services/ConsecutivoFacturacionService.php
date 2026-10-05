<?php

namespace App\Facturacion\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Core\Consecutivos\Models\ConsecutivoDocumento;
use App\Facturacion\Enums\TipoDocumentoFacturacion;
use App\Facturacion\Repositories\DocumentoFacturacionRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Prefijo y número inicial de la secuencia de cada tipo de documento de facturación, por empresa.
 * El prefijo se puede cambiar en cualquier momento (los números ya impresos conservan el suyo). El número
 * inicial solo mientras no haya documentos emitidos de ese tipo: cambiarlo después crearía duplicados.
 */
class ConsecutivoFacturacionService
{
    public function __construct(
        protected DocumentoFacturacionRepository $documentos,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return list<array{tipo: string, etiqueta: string, prefijo: ?string, siguiente: int, numero_inicial_editable: bool}> */
    public function listar(Usuario $usuario): array
    {
        return array_map(function (TipoDocumentoFacturacion $tipo) use ($usuario) {
            $fila = ConsecutivoDocumento::query()->where('tipo_documento', $tipo->consecutivo()->value)->first();

            return [
                'tipo' => $tipo->value,
                'etiqueta' => $tipo->etiqueta(),
                'prefijo' => $fila ? $fila->prefijo : $tipo->consecutivo()->prefijoInicial(),
                'siguiente' => ($fila?->ultimo_numero ?? 0) + 1,
                'numero_inicial_editable' => ! $this->documentos->existeEmitido($usuario->empresa_id, $tipo),
            ];
        }, TipoDocumentoFacturacion::cases());
    }

    public function configurar(TipoDocumentoFacturacion $tipo, Usuario $usuario, ?string $prefijo, ?int $numeroInicial): void
    {
        DB::transaction(function () use ($tipo, $usuario, $prefijo, $numeroInicial) {
            ConsecutivoDocumento::insertOrIgnore([
                'empresa_id' => $usuario->empresa_id,
                'tipo_documento' => $tipo->consecutivo()->value,
                'prefijo' => $tipo->consecutivo()->prefijoInicial(),
                'ultimo_numero' => 0,
                'creado_en' => now(),
                'ultima_actualizacion' => now(),
            ]);

            $fila = ConsecutivoDocumento::query()->where('tipo_documento', $tipo->consecutivo()->value)->lockForUpdate()->firstOrFail();

            if ($numeroInicial !== null) {
                if ($this->documentos->existeEmitido($usuario->empresa_id, $tipo)) {
                    throw ValidationException::withMessages(['numero_inicial' => __('validation.custom.facturacion.numeracion_bloqueada')]);
                }

                $fila->ultimo_numero = $numeroInicial - 1;
            }

            if ($prefijo !== null) {
                $fila->prefijo = $prefijo === '' ? null : $prefijo;
            }

            $fila->save();

            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_FACTURACION,
                "Consecutivo de {$tipo->etiqueta()} configurado",
                'consecutivo_documento',
                $fila->id,
                nuevos: ['prefijo' => $fila->prefijo, 'ultimo_numero' => $fila->ultimo_numero],
                autor: $usuario,
            );
        });
    }
}
