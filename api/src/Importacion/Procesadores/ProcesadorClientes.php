<?php

namespace App\Importacion\Procesadores;

use App\Clientes\Repositories\ClienteEmpleadorHistorialRepository;
use App\Clientes\Repositories\ClienteRepository;
use App\Clientes\Services\AsignacionAsesorService;
use App\Empleadores\Models\Empleador;
use App\Empleadores\Repositories\EmpleadorRepository;
use App\Importacion\Data\ContextoEjecucion;
use App\Importacion\Data\EmpleadorPendiente;
use App\Importacion\Data\FilaRechazada;
use App\Importacion\Lectura\ConvertidorValor;
use App\Importacion\Repositories\DatosImportacionRepository;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Reglas\ReglasPersona;
use App\Terceros\Repositories\TerceroRepository;
use App\Terceros\Services\ConstructorDatosTercero;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;

/** Clientes: persona natural con su tercero. Un cliente existente por cédula se reutiliza, no se duplica. */
final class ProcesadorClientes implements ProcesadorFila
{
    private const LONGITUD_MAXIMA_EMPLEADOR = 150;

    public function __construct(
        private readonly DatosImportacionRepository $datos,
        private readonly ClienteRepository $clientes,
        private readonly TerceroRepository $terceros,
        private readonly ConstructorDatosTercero $constructorTercero,
        private readonly AsignacionAsesorService $asignaciones,
        private readonly ClienteEmpleadorHistorialRepository $historialEmpleadores,
        private readonly EmpleadorRepository $empleadores,
    ) {}

    public function precargar(array $valoresLote, ContextoEjecucion $ctx): void
    {
        $cedulas = array_values(array_filter(array_column($valoresLote, 'cedula'), fn (string $cedula) => $cedula !== ''));

        $ctx->clientesPorCedula = $this->datos->clientesPorCedulas($ctx->empresaId, $cedulas);
    }

    public function procesar(array $valores, ContextoEjecucion $ctx): void
    {
        $cedula = $valores['cedula'];
        if ($cedula === '') {
            throw new FilaRechazada('La cédula es obligatoria.', 'cedula');
        }

        $partesNombre = preg_split('/\s+/', $valores['nombre'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($partesNombre) < 2) {
            throw new FilaRechazada('El nombre completo debe incluir nombres y apellidos.', 'nombre');
        }

        $cantidadApellidos = count($partesNombre) >= 3 ? 2 : 1;
        $datos = [
            'tipo_documento' => TipoDocumentoTercero::Cc->value,
            'numero_documento' => $cedula,
            'nombres' => implode(' ', array_slice($partesNombre, 0, -$cantidadApellidos)),
            'apellidos' => implode(' ', array_slice($partesNombre, -$cantidadApellidos)),
            ...$this->contacto($valores),
        ];

        $validador = Validator::make($datos, [
            ...ReglasPersona::natural(),
            'correo' => ['nullable', 'email', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'barrio' => ['nullable', 'string', 'max:100'],
            'ciudad' => ['nullable', 'string', 'max:100'],
        ]);

        if ($validador->fails()) {
            $columna = array_key_first($validador->errors()->toArray());
            throw new FilaRechazada($validador->errors()->first(), is_string($columna) ? $columna : null);
        }

        $fechaVinculo = ConvertidorValor::fecha($valores['fecha_vinculo'], 'fecha_vinculo');

        if (isset($ctx->clientesPorCedula[$cedula]) || ! $ctx->primeraVez('cliente|'.$cedula)) {
            return;
        }

        $empleadorId = $valores['empresa'] !== '' ? $this->resolverEmpleador($valores['empresa'], $ctx) : null;

        if (! $ctx->escribir) {
            return;
        }

        $tercero = $this->terceros->obtenerOCrear($this->constructorTercero->construir(TipoPersona::Natural, $datos));

        $cliente = $this->clientes->crear([
            'tercero_id' => $tercero->id,
            'empleador_id' => $empleadorId,
            'asesor_id' => $ctx->usuario->id,
            'activo' => true,
            'creado_por' => $ctx->usuario->id,
        ]);

        $this->asignaciones->registrarInicial($cliente, 'Importación histórica de clientes');

        if ($empleadorId !== null) {
            $this->historialEmpleadores->crear([
                'cliente_id' => $cliente->id,
                'empleador_id' => $empleadorId,
                'fecha_inicio' => $fechaVinculo ?? CarbonImmutable::now('America/Bogota')->toDateString(),
            ]);
        }

        $ctx->clientesPorCedula[$cedula] = $cliente->id;
    }

    /** @return array<string, string|null> */
    private function contacto(array $valores): array
    {
        $telefono = $valores['telefono'] !== '' ? preg_replace('/\D+/', '', $valores['telefono']) : '';
        $esCelular = preg_match('/^3\d{9}$/', (string) $telefono) === 1;

        return [
            'celular' => $esCelular ? $telefono : null,
            'telefono_fijo' => $telefono !== '' && ! $esCelular ? $telefono : null,
            'correo' => $valores['correo'] !== '' ? $valores['correo'] : null,
            'direccion' => $valores['direccion'] !== '' ? $valores['direccion'] : null,
            'barrio' => $valores['barrio'] !== '' ? $valores['barrio'] : null,
            'ciudad' => $valores['ciudad'] !== '' ? $valores['ciudad'] : null,
        ];
    }

    /**
     * Sin decisión del usuario el empleador faltante queda pendiente; con «crear» se da de alta una sola vez por nombre
     * y con «no crear» la fila se rechaza con el motivo.
     */
    private function resolverEmpleador(string $nombre, ContextoEjecucion $ctx): int
    {
        if (mb_strlen($nombre) > self::LONGITUD_MAXIMA_EMPLEADOR) {
            throw new FilaRechazada('El nombre del empleador supera los 150 caracteres.', 'empresa');
        }

        $clave = ConvertidorValor::nombreNormalizado($nombre);

        if (isset($ctx->empleadoresPorNombre[$clave])) {
            return $ctx->empleadoresPorNombre[$clave];
        }

        if ($ctx->crearEmpleadores === null) {
            $ctx->empleadoresFaltantes[$clave] ??= $nombre;

            throw new EmpleadorPendiente($nombre);
        }

        if ($ctx->crearEmpleadores === false) {
            throw new FilaRechazada("Empleador «{$nombre}» no creado: decidiste no crearlo.", 'empresa');
        }

        if (! $ctx->escribir) {
            return 0;
        }

        /** @var Empleador $empleador */
        $empleador = $this->empleadores->crear(['nombre' => trim($nombre)]);
        $ctx->empleadoresPorNombre[$clave] = $empleador->id;

        return $empleador->id;
    }
}
