<?php

namespace Tests\Concerns;

use App\Correo\Constants\CorreoConstants;
use App\Correo\Enums\TipoCorreo;
use App\Correo\Models\ConfiguracionCorreo;
use App\Correo\Models\PlantillaCorreo;
use App\Correo\Services\FabricaMailerCobranza;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;

/** Ningún test envía correo real: la fábrica entrega el fake de Mail, que solo registra. */
trait SimulaCorreo
{
    protected function simulaCorreo(): MailFake
    {
        $fake = Mail::fake();

        $this->app->instance(FabricaMailerCobranza::class, $this->fabricaQueEntrega($fake));

        return $fake;
    }

    protected function fabricaQueEntrega(Mailer $mailer): FabricaMailerCobranza
    {
        return new class($mailer) extends FabricaMailerCobranza
        {
            public function __construct(private readonly Mailer $mailer) {}

            public function paraConfiguracion(ConfiguracionCorreo $configuracion): Mailer
            {
                return $this->mailer;
            }
        };
    }

    protected function configuracionDeCorreo(TipoCorreo $tipo = TipoCorreo::Cobranza, array $overrides = []): ConfiguracionCorreo
    {
        $configuracion = new ConfiguracionCorreo(array_merge([
            'tipo' => $tipo,
            'remitente' => 'cobranza@creditosdda.com',
            'nombre_remitente' => 'Cobranza DDA',
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'puerto' => 587,
            'cifrado' => 'tls',
            'usuario' => 'cobranza@example.com',
        ], $overrides));

        $configuracion->empresa_id = (int) Auth::user()->empresa_id;
        $configuracion->clave = 'secreto-de-prueba';
        $configuracion->save();

        return $configuracion;
    }

    /** Primera plantilla del tipo: queda predeterminada automáticamente (misma regla que `PlantillasCorreoService::crear`). */
    protected function plantillaDeCorreo(TipoCorreo $tipo = TipoCorreo::Cobranza, array $overrides = []): PlantillaCorreo
    {
        $esCobranza = $tipo === TipoCorreo::Cobranza;

        $plantilla = new PlantillaCorreo(array_merge([
            'tipo' => $tipo,
            'nombre' => 'Plantilla de prueba',
            'asunto' => $esCobranza ? CorreoConstants::PLANTILLA_INICIAL_ASUNTO_COBRANZA : CorreoConstants::PLANTILLA_INICIAL_ASUNTO_FACTURACION,
            'cuerpo' => $esCobranza ? CorreoConstants::PLANTILLA_INICIAL_CUERPO_COBRANZA : CorreoConstants::PLANTILLA_INICIAL_CUERPO_FACTURACION,
            'es_predeterminada' => true,
        ], $overrides));

        $plantilla->empresa_id = (int) Auth::user()->empresa_id;
        $plantilla->save();

        return $plantilla;
    }
}
