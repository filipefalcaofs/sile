<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * E-mail de teste de configuração de servidor SMTP (ConfigEmail). Enviado de
 * verdade pelo EmailServerConnectionTester com a credencial cadastrada — a prova
 * real de que o servidor funciona (anti-fachada), não uma simulação.
 */
class EmailServerTestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        private string $fromAddress,
        private string $fromName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: 'Teste de configuração de e-mail — SILE',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>Teste de configuração de e-mail do SILE. Se você recebeu esta mensagem, o servidor de e-mail está operacional.</p>',
        );
    }
}
