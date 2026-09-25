<?php

namespace Tests\Feature\Contabilidad;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\ActsAsAdmin;
use Tests\TestCase;

class ImportarTercerosMigracionAperturaTest extends TestCase
{
    use ActsAsAdmin;
    use RefreshDatabase;

    private const ENCABEZADO = 'tipo,tipo_persona,tipo_documento,numero_documento,nombres,apellidos,razon_social,digito_verificacion,representante_legal';

    private function importar(array $filas): array
    {
        $csv = self::ENCABEZADO."\n".implode("\n", $filas)."\n";

        return $this->postJson('/api/v1/contabilidad/migraciones-apertura', [
            'tipo' => 'terceros',
            'archivo' => UploadedFile::fake()->createWithContent('terceros.csv', $csv),
        ])->assertCreated()->json('migracion');
    }

    public function test_importa_terceros_naturales_y_juridicos_con_el_tipo_de_persona_de_cada_fila(): void
    {
        $this->actingAsAdmin();

        $migracion = $this->importar([
            'proveedor,natural,cc,1000000001,Ana,Torres,,,',
            'proveedor,juridica,,900123456,,,Comercial Andina SAS,8,Carlos Pérez',
            'otro,juridica,,800197268,,,DIAN,4,Director',
        ]);

        $this->assertSame(3, $migracion['registros_aceptados']);
        $this->assertSame(0, $migracion['registros_rechazados']);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural', 'nombre' => 'Ana Torres']);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '900123456', 'tipo_persona' => 'juridica', 'tipo_documento' => 'nit', 'razon_social' => 'Comercial Andina SAS']);
        $this->assertDatabaseCount('proveedores', 2);
        $this->assertDatabaseCount('otro_terceros', 1);
    }

    public function test_rechaza_las_filas_sin_tipo_de_persona_o_con_datos_incoherentes(): void
    {
        $this->actingAsAdmin();

        $migracion = $this->importar([
            'proveedor,,cc,1000000001,Ana,Torres,,,',
            'proveedor,natural,nit,1000000002,Luis,Gómez,,,',
            'proveedor,juridica,,900123456,,,Comercial Andina SAS,3,Carlos Pérez',
            'cliente,juridica,,900123457,,,Empresa SAS,'.'5'.',Rep',
        ]);

        $this->assertSame(0, $migracion['registros_aceptados']);
        $this->assertSame(4, $migracion['registros_rechazados']);
        $this->assertDatabaseCount('terceros', 0);
    }
}
