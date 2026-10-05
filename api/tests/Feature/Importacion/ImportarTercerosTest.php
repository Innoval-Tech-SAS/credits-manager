<?php

namespace Tests\Feature\Importacion;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\ImportaArchivos;
use Tests\TestCase;

class ImportarTercerosTest extends TestCase
{
    use ActsAsAdmin;
    use ImportaArchivos;
    use RefreshDatabase;

    private const ENCABEZADO = 'tipo,tipo_persona,tipo_documento,numero_documento,nombres,apellidos,razon_social,digito_verificacion,representante_legal';

    private function csv(array $filas): string
    {
        return self::ENCABEZADO."\n".implode("\n", $filas)."\n";
    }

    public function test_importa_terceros_naturales_y_juridicos_con_el_tipo_de_persona_de_cada_fila(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $id = $this->importarCompleto('terceros', $this->csv([
            'proveedor,natural,cc,1000000001,Ana,Torres,,,',
            'proveedor,juridica,,900123456,,,Comercial Andina SAS,8,Carlos Pérez',
            'otro,juridica,,800197268,,,DIAN,4,Director',
        ]));

        $this->assertSame(3, $this->importacion($id)->filas_aceptadas);
        $this->assertSame(0, $this->importacion($id)->filas_rechazadas);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '1000000001', 'tipo_persona' => 'natural', 'nombre' => 'Ana Torres']);
        $this->assertDatabaseHas('terceros', ['numero_documento' => '900123456', 'tipo_persona' => 'juridica', 'tipo_documento' => 'nit', 'razon_social' => 'Comercial Andina SAS']);
        $this->assertDatabaseCount('proveedores', 2);
        $this->assertDatabaseCount('otro_terceros', 1);
    }

    public function test_rechaza_las_filas_sin_tipo_de_persona_o_con_datos_incoherentes(): void
    {
        $this->actingAsAdmin();
        $this->prepararImportaciones();

        $id = $this->importarCompleto('terceros', $this->csv([
            'proveedor,,cc,1000000001,Ana,Torres,,,',
            'proveedor,natural,nit,1000000002,Luis,Gómez,,,',
            'proveedor,juridica,,900123456,,,Comercial Andina SAS,3,Carlos Pérez',
            'cliente,juridica,,900123457,,,Empresa SAS,5,Rep',
        ]));

        $this->assertSame(0, $this->importacion($id)->filas_aceptadas);
        $this->assertSame(4, $this->importacion($id)->filas_rechazadas);
        $this->assertDatabaseCount('terceros', 0);
    }
}
