<?php

namespace App\Exportacion\Models;

use App\Core\Base\BaseModel;
use App\Core\Concerns\PerteneceAEmpresa;
use App\Empresas\Models\Empresa;
use App\Exportacion\Enums\EstadoExportacion;
use App\Exportacion\Enums\FormatoExportacion;
use App\Usuarios\Models\Usuario;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'empresa_id', 'usuario_id', 'reporte', 'formato', 'estado', 'filtros', 'filas',
    'nombre_archivo', 'ruta_archivo', 'tamano_bytes', 'error', 'iniciada_en', 'completada_en',
])]
#[Hidden(['ruta_archivo'])]
class Exportacion extends BaseModel
{
    use PerteneceAEmpresa;

    protected $table = 'exportaciones';

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    protected function casts(): array
    {
        return [
            'empresa_id' => 'integer',
            'usuario_id' => 'integer',
            'formato' => FormatoExportacion::class,
            'estado' => EstadoExportacion::class,
            'filtros' => 'array',
            'filas' => 'integer',
            'tamano_bytes' => 'integer',
            'iniciada_en' => 'datetime',
            'completada_en' => 'datetime',
        ];
    }
}
