<?php

namespace Tests\Concerns;

use App\Correo\Models\ConfiguracionCorreo;
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

    protected function configuracionDeCorreo(array $overrides = []): ConfiguracionCorreo
    {
        $configuracion = new ConfiguracionCorreo(array_merge([
            'remitente' => 'cobranza@creditosdda.com',
            'nombre_remitente' => 'Cobranza DDA',
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'puerto' => 587,
            'cifrado' => 'tls',
            'usuario' => 'cobranza@example.com',
            'plantilla_asunto' => 'Recordatorio - {empresa}',
            'plantilla_cuerpo' => '<p>Hola {cliente}, la cuota {cuota} por {valor} vence el {fecha_vencimiento}.</p>',
        ], $overrides));

        $configuracion->empresa_id = (int) Auth::user()->empresa_id;
        $configuracion->clave = 'secreto-de-prueba';
        $configuracion->save();

        return $configuracion;
    }
}
