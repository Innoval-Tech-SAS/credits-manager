<?php

namespace App\Correo\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Enums\AccionAuditoria;
use App\Auditoria\Services\EmisorAuditoria;
use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\PlantillaCorreo;
use App\Correo\Repositories\PlantillaCorreoRepository;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Catálogo de plantillas (varias por tipo, con nombre). El renderizado puro vive en `PlantillaCorreoService`. */
class PlantillasCorreoService
{
    public function __construct(
        protected PlantillaCorreoRepository $plantillas,
        protected PlantillaCorreoService $renderizador,
        protected EmisorAuditoria $auditoria,
    ) {}

    /** @return Collection<int, PlantillaCorreo> */
    public function listar(Usuario $usuario, TipoCorreo $tipo): Collection
    {
        return $this->plantillas->deEmpresaYTipo((int) $usuario->empresa_id, $tipo);
    }

    public function crear(Usuario $usuario, TipoCorreo $tipo, array $datos): PlantillaCorreo
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($empresaId, $tipo, $datos, $usuario) {
            $esPrimera = $this->plantillas->hermanasBloqueadas($empresaId, $tipo)->isEmpty();

            $plantilla = new PlantillaCorreo([
                'tipo' => $tipo,
                'nombre' => $datos['nombre'],
                'asunto' => $datos['asunto'],
                'cuerpo' => $this->renderizador->sanitizarHtml($datos['cuerpo']),
                'es_predeterminada' => $esPrimera,
                'actualizado_por' => $usuario->id,
            ]);
            $plantilla->empresa_id = $empresaId;
            $plantilla->save();

            $this->auditoria->registrarCreacion(AuditoriaConstants::MODULO_CORREO, "Plantilla de correo «{$plantilla->nombre}» creada ({$tipo->value})", $plantilla);

            return $plantilla;
        });
    }

    public function actualizar(Usuario $usuario, PlantillaCorreo $plantilla, array $datos): PlantillaCorreo
    {
        $anteriores = $this->auditoria->instantanea($plantilla);

        $plantilla->fill([
            'nombre' => $datos['nombre'],
            'asunto' => $datos['asunto'],
            'cuerpo' => $this->renderizador->sanitizarHtml($datos['cuerpo']),
            'actualizado_por' => $usuario->id,
        ]);
        $plantilla->save();

        $this->auditoria->registrarModificacion(AuditoriaConstants::MODULO_CORREO, "Plantilla de correo «{$plantilla->nombre}» modificada", $plantilla, $anteriores);

        return $plantilla;
    }

    /** No se puede eliminar la predeterminada: el usuario debe marcar otra primero (o es la única y no hay otra que marcar). */
    public function eliminar(Usuario $usuario, PlantillaCorreo $plantilla): void
    {
        if ($plantilla->es_predeterminada) {
            throw ValidationException::withMessages(['plantilla' => __('validation.custom.correo.elimina_predeterminada')]);
        }

        $this->auditoria->registrarEliminacion(AuditoriaConstants::MODULO_CORREO, "Plantilla de correo «{$plantilla->nombre}» eliminada", $plantilla);

        $plantilla->delete();
    }

    public function marcarPredeterminada(Usuario $usuario, PlantillaCorreo $plantilla): PlantillaCorreo
    {
        $empresaId = (int) $usuario->empresa_id;

        return DB::transaction(function () use ($empresaId, $plantilla, $usuario) {
            $hermanas = $this->plantillas->hermanasBloqueadas($empresaId, $plantilla->tipo);

            foreach ($hermanas as $hermana) {
                if ($hermana->is($plantilla) || ! $hermana->es_predeterminada) {
                    continue;
                }

                $hermana->es_predeterminada = false;
                $hermana->save();
            }

            $plantilla->es_predeterminada = true;
            $plantilla->actualizado_por = $usuario->id;
            $plantilla->save();

            $this->auditoria->registrar(
                AccionAuditoria::Modificar,
                AuditoriaConstants::MODULO_CORREO,
                "Plantilla de correo «{$plantilla->nombre}» marcada como predeterminada",
                'plantilla_correo',
                $plantilla->id,
                autor: $usuario,
            );

            return $plantilla;
        });
    }

    /** Vista previa con datos de ejemplo: no toca la base ni envía nada. */
    public function vistaPrevia(TipoCorreo $tipo, string $asunto, string $cuerpo): array
    {
        $ejemplo = CorreoConstants::ejemploVistaPrevia($tipo);

        return [
            'asunto' => $this->renderizador->renderizarAsunto($asunto, $ejemplo),
            'cuerpo' => $this->renderizador->renderizarCuerpo($this->renderizador->sanitizarHtml($cuerpo), $ejemplo),
        ];
    }
}
