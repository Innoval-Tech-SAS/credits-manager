<?php

namespace Tests\Unit\Correo;

use App\Core\Dinero\Dinero;
use App\Correo\Enums\CifradoCorreo;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Services\FabricaMailerCobranza;
use App\Correo\Services\PlantillaCorreoService;
use Tests\TestCase;

class PlantillaCorreoServiceTest extends TestCase
{
    private PlantillaCorreoService $plantillas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plantillas = new PlantillaCorreoService;
    }

    public function test_detecta_variables_que_no_existen(): void
    {
        $this->assertSame(['clente'], PlantillaCorreoService::variablesDesconocidas('Hola {cliente}, {clente}', TipoCorreo::Cobranza));
        $this->assertSame([], PlantillaCorreoService::variablesDesconocidas('Hola {cliente} {valor}', TipoCorreo::Cobranza));
    }

    public function test_detecta_variables_que_no_existen_segun_el_tipo(): void
    {
        // "valor" es de cobranza; facturación usa "valor_total" en su lugar.
        $this->assertSame(['valor'], PlantillaCorreoService::variablesDesconocidas('{cliente} {valor}', TipoCorreo::Facturacion));
        $this->assertSame([], PlantillaCorreoService::variablesDesconocidas('{cliente} {valor_total}', TipoCorreo::Facturacion));
    }

    public function test_escapa_los_valores_del_html_pero_no_la_plantilla(): void
    {
        $html = $this->plantillas->renderizarCuerpo('<p>{cliente}</p>', ['cliente' => '<b>Ana & "Co"</b>']);

        $this->assertSame('<p>&lt;b&gt;Ana &amp; &quot;Co&quot;&lt;/b&gt;</p>', $html);
    }

    public function test_un_valor_con_llaves_no_se_vuelve_a_sustituir(): void
    {
        $html = $this->plantillas->renderizarCuerpo('<p>{cliente}</p><p>{empresa}</p>', ['cliente' => '{empresa}', 'empresa' => 'DDA']);

        $this->assertSame('<p>{empresa}</p><p>DDA</p>', $html);
    }

    public function test_el_sanitizador_quita_scripts_iframes_manejadores_y_javascript(): void
    {
        $html = $this->plantillas->sanitizarHtml('<p onload="x()">Hola</p><script>alert(1)</script><iframe src="x"></iframe><img src="javascript:alert(1)"><a href="https://dda.co">DDA</a>');

        $this->assertSame('<p>Hola</p><img src="#"><a href="https://dda.co">DDA</a>', $html);
    }

    public function test_el_asunto_queda_en_una_linea_sin_html(): void
    {
        $this->assertSame('Recordatorio - DDA', $this->plantillas->renderizarAsunto("<b>Recordatorio</b>\n- {empresa}", ['empresa' => 'DDA']));
    }

    public function test_formatea_pesos_en_formato_colombiano_sin_float(): void
    {
        $this->assertSame('$ 350.000,00', $this->plantillas->formatearPesos(Dinero::desdePesos(350000)));
        $this->assertSame('$ 1.234.567,89', $this->plantillas->formatearPesos(Dinero::desdeCentavos(123456789)));
        $this->assertSame('-$ 12,50', $this->plantillas->formatearPesos(Dinero::desdeCentavos(-1250)));
    }

    public function test_la_fabrica_usa_smtps_con_ssl(): void
    {
        $fabrica = new FabricaMailerCobranza;

        $smtp = new ConfiguracionCorreo(['host' => 'smtp.example.com', 'puerto' => 465, 'cifrado' => CifradoCorreo::Ssl, 'usuario' => 'u']);
        $smtp->clave = 'clave';
        $transporte = $fabrica->transporte($smtp);

        $this->assertSame('smtp', $transporte['transport']);
        $this->assertSame('smtps', $transporte['scheme']);
        $this->assertSame('clave', $transporte['password']);
    }
}
