<?php

namespace App\Services\Email;

use App\Mail\EmailServerTestMail;
use App\Models\EmailServer;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Teste de conexão REAL de um servidor de e-mail (HU-014 / parametrização):
 * envia um e-mail de teste de verdade usando a credencial cadastrada, num mailer
 * SMTP ad-hoc montado a partir da configuração — sem tocar o mailer padrão da
 * aplicação. Sucesso/erro reais; nunca finge envio (anti-fachada).
 */
class EmailServerConnectionTester
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function test(EmailServer $server, string $recipient): array
    {
        try {
            config(['mail.mailers.email_server_test' => [
                'transport' => 'smtp',
                'host' => $server->host,
                'port' => $server->port,
                'username' => $server->username,
                'password' => $server->password,
                'scheme' => $server->scheme,
                'timeout' => $server->timeout,
            ]]);

            Mail::mailer('email_server_test')
                ->to($recipient)
                ->send(new EmailServerTestMail($server->from_address, $server->from_name));

            return ['ok' => true, 'message' => "E-mail de teste enviado para {$recipient}."];
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }
}
