<?php

namespace App\Correo\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Factura o nota de facturación con su PDF adjunto. Asunto y cuerpo son fijos, no una plantilla de la empresa. */
class CorreoDocumentoFacturacion extends Mailable
{
    public function __construct(
        public readonly string $asunto,
        public readonly string $cuerpoHtml,
        public readonly string $destinatario,
        public readonly string $remitente,
        public readonly string $nombreRemitente,
        public readonly string $pdfContenido,
        public readonly string $pdfNombreArchivo,
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

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContenido, $this->pdfNombreArchivo.'.pdf')->withMime('application/pdf'),
        ];
    }
}
