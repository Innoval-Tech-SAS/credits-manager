<?php

namespace Tests\Unit\Correo;

use App\Correo\Enums\CifradoCorreo;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Support\ContextoFalloCorreo;
use RuntimeException;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ContextoFalloCorreoTest extends TestCase
{
    private function configuracion(array $overrides = []): ConfiguracionCorreo
    {
        $configuracion = new ConfiguracionCorreo(array_merge([
            'host' => 'smtp.example.com',
            'puerto' => 587,
            'cifrado' => CifradoCorreo::Tls,
            'remitente' => 'cobranza@creditosdda.com',
            'usuario' => 'cobranza@example.com',
        ], $overrides));

        $configuracion->clave = 'secreto-de-prueba';

        return $configuracion;
    }

    public function test_incluye_clase_mensaje_codigo_y_duracion(): void
    {
        $excepcion = new RuntimeException('Fallo genérico', 7);

        $contexto = ContextoFalloCorreo::construir($this->configuracion(), $excepcion, 123.456);

        $this->assertSame(RuntimeException::class, $contexto['excepcion']);
        $this->assertSame('Fallo genérico', $contexto['mensaje']);
        $this->assertSame(7, $contexto['codigo']);
        $this->assertSame(123.46, $contexto['duracion_ms']);
        $this->assertNull($contexto['debug']);
    }

    public function test_nunca_filtra_usuario_ni_clave_en_texto_plano_o_base64(): void
    {
        $configuracion = $this->configuracion();
        $excepcion = new RuntimeException('Autenticación fallida para cobranza@example.com con clave secreto-de-prueba');

        $contexto = ContextoFalloCorreo::construir($configuracion, $excepcion, 10.0);

        $this->assertStringNotContainsString('secreto-de-prueba', $contexto['mensaje']);
        $this->assertStringNotContainsString('cobranza@example.com', $contexto['mensaje']);
        $this->assertStringNotContainsString(base64_encode('secreto-de-prueba'), $contexto['mensaje']);
    }

    public function test_debug_de_transporte_se_trunca_y_redacta_el_intercambio_auth(): void
    {
        $excepcion = new TransportException('No se pudo autenticar');
        $excepcion->appendDebug(
            "<< 220 smtp.example.com ESMTP\n".
            ">> EHLO cliente\n".
            ">> AUTH LOGIN\n".
            "<< 334 VXNlcm5hbWU6\n".
            ">> Y29icmFuemFAZXhhbXBsZS5jb20=\n".
            "<< 334 UGFzc3dvcmQ6\n".
            ">> c2VjcmV0by1kZS1wcnVlYmE=\n".
            '<< 535 Authentication failed'
        );

        $contexto = ContextoFalloCorreo::construir($this->configuracion(), $excepcion, 5.0);

        $this->assertStringNotContainsString('Y29icmFuemFAZXhhbXBsZS5jb20=', $contexto['debug']);
        $this->assertStringNotContainsString('c2VjcmV0by1kZS1wcnVlYmE=', $contexto['debug']);
        $this->assertStringContainsString('535 Authentication failed', $contexto['debug']);
    }

    public function test_debug_largo_se_trunca_a_las_ultimas_lineas(): void
    {
        $excepcion = new TransportException('Fallo de conexión');
        $excepcion->appendDebug(str_repeat('a', 5000).'cola-visible');

        $contexto = ContextoFalloCorreo::construir($this->configuracion(), $excepcion, 1.0);

        $this->assertLessThanOrEqual(4000, mb_strlen($contexto['debug']));
        $this->assertStringEndsWith('cola-visible', $contexto['debug']);
    }

    public function test_cadena_de_excepciones_previas_se_limita_a_cinco(): void
    {
        $actual = null;

        for ($indice = 0; $indice < 8; $indice++) {
            $actual = new RuntimeException("Nivel {$indice}", previous: $actual);
        }

        $contexto = ContextoFalloCorreo::construir($this->configuracion(), $actual, 1.0);

        $this->assertCount(5, $contexto['previas']);
        $this->assertSame('Nivel 6', $contexto['previas'][0]['mensaje']);
    }

    public function test_funciona_sin_configuracion(): void
    {
        $contexto = ContextoFalloCorreo::construir(null, new RuntimeException('Sin configuración'), 1.0);

        $this->assertNull($contexto['configuracion']['host']);
        $this->assertFalse($contexto['configuracion']['usuario_configurado']);
    }
}
