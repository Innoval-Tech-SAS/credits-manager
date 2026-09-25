<?php

namespace App\Proveedores\Services;

use App\Auditoria\Constants\AuditoriaConstants;
use App\Auditoria\Services\EmisorAuditoria;
use App\Proveedores\Models\Proveedor;
use App\Proveedores\Repositories\ProveedorRepository;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use App\Terceros\Repositories\TerceroRepository;
use App\Terceros\Services\ConstructorDatosTercero;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ProveedorService
{
    public function __construct(
        protected ProveedorRepository $proveedores,
        protected TerceroRepository $terceros,
        protected ConstructorDatosTercero $datosTercero,
        protected EmisorAuditoria $auditoria,
    ) {}

    public function listar(?string $busqueda, int $porPagina, int $pagina): LengthAwarePaginator
    {
        return $this->proveedores->paginar($busqueda, max(1, min(100, $porPagina)), max(1, $pagina));
    }

    public function crear(TipoPersona $tipoPersona, array $datos): Proveedor
    {
        return DB::transaction(function () use ($tipoPersona, $datos) {
            $tercero = $this->terceros->obtenerOCrear($this->datosTercero->construir($tipoPersona, $datos));

            $proveedor = $this->proveedores->crear(['tercero_id' => $tercero->id, 'activo' => true]);

            $this->auditoria->registrarCreacion(
                AuditoriaConstants::MODULO_PROVEEDORES,
                "Proveedor {$tercero->nombre} creado",
                $proveedor,
                [...$this->datosDelTercero($tercero, $proveedor), 'tercero_reutilizado' => ! $tercero->wasRecentlyCreated],
            );

            return $proveedor->setAttribute('tercero_reutilizado', ! $tercero->wasRecentlyCreated);
        });
    }

    public function editar(Proveedor $proveedor, array $datos): Proveedor
    {
        return DB::transaction(function () use ($proveedor, $datos) {
            $anteriores = [...$this->auditoria->instantanea($proveedor), ...$this->datosDelTercero($proveedor->tercero, $proveedor)];

            $this->terceros->actualizar($proveedor->tercero, $this->datosTercero->construir($proveedor->tercero->tipo_persona, $datos));

            $proveedor = $this->proveedores->actualizar($proveedor, [
                ...(array_key_exists('activo', $datos) ? ['activo' => $datos['activo']] : []),
            ]);

            $this->auditoria->registrarModificacion(
                AuditoriaConstants::MODULO_PROVEEDORES,
                "Proveedor {$proveedor->tercero->nombre} modificado",
                $proveedor,
                $anteriores,
                adicionales: $this->datosDelTercero($proveedor->tercero, $proveedor),
            );

            return $proveedor;
        });
    }

    /** Datos propios del tercero: las columnas que también tiene el proveedor (`id`, `empresa_id`, `activo`) ganan las del proveedor. */
    private function datosDelTercero(Tercero $tercero, Model $principal): array
    {
        return Arr::except($this->auditoria->instantanea($tercero), array_keys($this->auditoria->instantanea($principal)));
    }
}
