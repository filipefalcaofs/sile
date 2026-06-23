<?php

namespace Tests\Feature\Email;

use App\Mail\EmailServerTestMail;
use Illuminate\Mail\Mailables\Address;
use Tests\TestCase;

class EmailServerTestMailTest extends TestCase
{
    public function test_o_envelope_usa_o_remetente_configurado_com_o_address_do_laravel(): void
    {
        $mailable = new EmailServerTestMail('no-reply@sedur.test', 'SEDUR Salvador');

        // Montar o envelope reproduz o caminho real do envio: o Envelope do
        // Laravel só aceita Illuminate\Mail\Mailables\Address (ou string), nunca
        // o Symfony\Component\Mime\Address.
        $envelope = $mailable->envelope();

        $this->assertInstanceOf(Address::class, $envelope->from);
        $this->assertSame('no-reply@sedur.test', $envelope->from->address);
        $this->assertSame('SEDUR Salvador', $envelope->from->name);
        $this->assertSame('Teste de configuração de e-mail — SILE', $envelope->subject);
    }
}
