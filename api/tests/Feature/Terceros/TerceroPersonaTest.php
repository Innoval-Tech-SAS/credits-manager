<?php

namespace Tests\Feature\Terceros;

use App\Impresion\Data\DestinatarioDocumento;
use App\Terceros\Enums\TipoDocumentoTercero;
use App\Terceros\Enums\TipoPersona;
use App\Terceros\Models\Tercero;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsAdmin;
use Tests\Concerns\CreatesContabilidadFixtures;
use Tests\TestCase;

class TerceroPersonaTest extends TestCase
{
    use ActsAsAdmin;
    use CreatesContabilidadFixtures;
    use RefreshDatabase;

    public function test_el_nombre_de_una_persona_natural_se_deriva_de_nombres_y_apellidos(): void
    {
        $this->actingAsAdmin();

        $tercero = $this->crearTercero(['tipo_persona' => TipoPersona::Natural, 'nombre' => 'Ana María Torres']);
        $this->assertSame('Ana María Torres', $tercero->nombre);

        $tercero->update(['nombres' => 'Ana', 'apellidos' => 'Rojas']);
        $this->assertSame('Ana Rojas', $tercero->fresh()->nombre);
    }

    public function test_el_nombre_de_una_persona_juridica_es_su_razon_social(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['nombre' => 'Comercial Andina SAS']);

        $tercero->update(['razon_social' => 'Comercial Andina Colombia SAS']);

        $this->assertSame('Comercial Andina Colombia SAS', $tercero->fresh()->nombre);
    }

    public function test_el_tipo_de_persona_es_obligatorio_en_la_base(): void
    {
        $this->actingAsAdmin();

        $this->expectException(QueryException::class);

        Tercero::create(['tipo_documento' => TipoDocumentoTercero::Cc, 'numero_documento' => '123', 'nombre' => 'Sin tipo']);
    }

    public function test_el_destinatario_de_una_persona_juridica_lleva_el_nit_con_su_digito(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['numero_documento' => '900123456', 'nombre' => 'Comercial Andina SAS']);

        $destinatario = DestinatarioDocumento::desdeTercero($tercero);

        $this->assertSame('Comercial Andina SAS', $destinatario->nombre);
        $this->assertSame('900123456-8', $destinatario->documento);
        $this->assertSame('NIT', $destinatario->etiquetaDocumento);
    }

    public function test_el_destinatario_de_una_persona_natural_lleva_el_nombre_completo_y_la_cedula(): void
    {
        $this->actingAsAdmin();
        $tercero = $this->crearTercero(['tipo_persona' => TipoPersona::Natural, 'numero_documento' => '1000000001', 'nombre' => 'Ana Torres']);

        $destinatario = DestinatarioDocumento::desdeTercero($tercero);

        $this->assertSame('Ana Torres', $destinatario->nombre);
        $this->assertSame('1000000001', $destinatario->documento);
        $this->assertSame('CC', $destinatario->etiquetaDocumento);
    }
}
