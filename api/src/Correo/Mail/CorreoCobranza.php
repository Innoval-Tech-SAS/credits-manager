<?php

namespace App\Correo\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Correo de cobranza ya renderizado: el HTML llega completo, no hay vista de Blade que resolver. */
class CorreoCobranza extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $cuerpoHtml,
        public readonly string $destinatario,
        public readonly string $remitente,
        public readonly string $nombreRemitente,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->remitente, $this->nombreRemitente),
            to: [$this->destinatario],
            subject: $this->asunto,
        );
    }

    public function content(): Content
    {
        return new Content(htmlString: $this->cuerpoHtml);
    }
}
