<?php

namespace App\Providers;

use App\Models\EmailServer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Ponte de runtime do servidor de e-mail administrável (ConfigEmail) com o mailer
 * do Laravel. No boot, aplica o servidor padrão ATIVO do banco sobre `config('mail.*')`.
 *
 * Degradação segura (anti-fachada, sem quebrar o que já existe): sem servidor
 * cadastrado/ativo, NADA é alterado e o mailer do `.env` (hoje Resend) permanece.
 * Assim o envio só passa a usar o banco quando o administrador cadastra e marca um
 * servidor padrão — exatamente o que torna a tela uma feature real, não decorativa.
 */
class MailConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->applyDefaultEmailServer();
    }

    /**
     * Aplica o servidor de e-mail padrão ativo do banco na config do mailer SMTP.
     * Idempotente e seguro fora de um banco migrado (boot durante migrate/CI).
     */
    public function applyDefaultEmailServer(): void
    {
        try {
            if (! Schema::hasTable('email_servers')) {
                return;
            }

            $server = EmailServer::query()
                ->where('is_default', true)
                ->where('active', true)
                ->first();
        } catch (Throwable) {
            // Banco indisponível (ex.: boot antes da migração) — mantém o .env.
            return;
        }

        if ($server === null) {
            return;
        }

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $server->host,
            'mail.mailers.smtp.port' => $server->port,
            'mail.mailers.smtp.username' => $server->username,
            'mail.mailers.smtp.password' => $server->password,
            'mail.mailers.smtp.scheme' => $this->scheme($server->encryption),
            'mail.mailers.smtp.timeout' => $server->timeout,
            'mail.from.address' => $server->from_address,
            'mail.from.name' => $server->from_name,
        ]);
    }

    /**
     * Mapeia a criptografia administrável para o scheme do transporte SMTP do
     * Symfony Mailer: tls (STARTTLS), smtps (TLS implícito/SSL) ou nenhum.
     */
    private function scheme(string $encryption): ?string
    {
        return match ($encryption) {
            'tls' => 'tls',
            'ssl' => 'smtps',
            default => null,
        };
    }
}
