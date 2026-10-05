<?php

namespace Tests\Concerns;

use App\Clientes\Models\Cliente;
use App\Creditos\Models\Credito;
use App\Empresas\Models\Empresa;
use App\Metas\Constants\MetasConstants;
use App\Metas\Enums\TipoMeta;
use App\Metas\Models\ConfiguracionMeta;
use App\Metas\Models\Meta;
use App\Metas\Models\MetaAsesor;
use App\Pagos\Enums\FormaPago;
use App\Pagos\Models\Pago;
use App\Usuarios\Models\Rol;
use App\Usuarios\Models\Usuario;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Requiere `CreatesCreditFixtures` y un usuario autenticado (ActsAsAdmin): `empresa_id` sale de ese usuario. */
trait CreatesMetasFixtures
{
    protected function simularHoy(string $fecha): void
    {
        Carbon::setTestNow(Carbon::parse($fecha, MetasConstants::ZONA_HORARIA));
    }

    protected function crearAsesor(Empresa $empresa, string $nombre = 'Asesor de prueba'): Usuario
    {
        $rol = Rol::firstOrCreate(['empresa_id' => $empresa->id, 'nombre' => 'asesor']);

        return Usuario::create([
            'empresa_id' => $empresa->id,
            'nombre' => $nombre,
            'usuario' => 'asesor_'.uniqid(),
            'password_hash' => 'irrelevante-en-tests',
            'rol_id' => $rol->id,
            'activo' => true,
        ]);
    }

    /** El historial es de solo inserción: se escribe directo para fechar la asignación. */
    protected function asignarAsesor(Cliente $cliente, Usuario $asesor, string $cuando): void
    {
        DB::table('cliente_asesor_historial')->insert([
            'empresa_id' => $cliente->empresa_id,
            'cliente_id' => $cliente->id,
            'asesor_anterior_id' => null,
            'asesor_id' => $asesor->id,
            'asignado_por' => null,
            'motivo' => null,
            'creado_en' => $cuando,
        ]);
    }

    protected function crearMeta(string $periodo, TipoMeta $tipo, int $pesos): Meta
    {
        return Meta::create([
            'periodo' => $periodo,
            'tipo' => $tipo->value,
            'valor_objetivo' => $pesos,
            'creado_por' => Auth::id(),
        ]);
    }

    protected function distribuirMeta(Meta $meta, Usuario $asesor, int $pesos): MetaAsesor
    {
        return MetaAsesor::create([
            'meta_id' => $meta->id,
            'usuario_id' => $asesor->id,
            'valor_objetivo' => $pesos,
        ]);
    }

    /** Escribe o reemplaza un parámetro de la empresa autenticada. */
    protected function configurarMetas(string $clave, bool|int|string $valor): void
    {
        $texto = is_bool($valor) ? ($valor ? '1' : '0') : (string) $valor;

        ConfiguracionMeta::query()->updateOrCreate(['clave' => $clave], ['valor' => $texto]);
    }

    protected function crearPago(Cliente $cliente, Credito $credito, int $pesos, string $fecha, int $capital, int $interes): Pago
    {
        return Pago::create([
            'credito_id' => $credito->id,
            'cliente_id' => $cliente->id,
            'valor_pago' => $pesos,
            'valor_capital' => $capital,
            'valor_interes' => $interes,
            'fecha_pago' => $fecha,
            'forma_pago' => FormaPago::Efectivo,
            'registrado_por' => Auth::id(),
        ]);
    }
}
