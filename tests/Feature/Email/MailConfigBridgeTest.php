<?php

namespace Tests\Feature\Email;

use App\Models\EmailServer;
use App\Providers\MailConfigServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MailConfigBridgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function applyBridge(): void
    {
        (new MailConfigServiceProvider($this->app))->applyDefaultEmailServer();
    }

    public function test_servidor_padrao_ativo_sobrescreve_a_config_do_mailer(): void
    {
        EmailServer::factory()->create([
            'is_default' => true,
            'active' => true,
            'host' => 'smtp.sedur.test',
            'port' => 2525,
            'encryption' => 'tls',
            'username' => 'sedur',
            'password' => 'segredo-1234ABCD',
            'from_address' => 'no-reply@sedur.test',
            'from_name' => 'SEDUR Salvador',
        ]);

        $this->applyBridge();

        $this->assertSame('smtp', config('mail.default'));
        $this->assertSame('smtp.sedur.test', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('sedur', config('mail.mailers.smtp.username'));
        $this->assertSame('segredo-1234ABCD', config('mail.mailers.smtp.password'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'), 'encryption tls deve virar o scheme "smtp" (STARTTLS), nunca "tls".');
        $this->assertSame('no-reply@sedur.test', config('mail.from.address'));
        $this->assertSame('SEDUR Salvador', config('mail.from.name'));
    }

    public function test_a_criptografia_ssl_vira_o_scheme_smtps(): void
    {
        EmailServer::factory()->default()->create([
            'active' => true,
            'encryption' => 'ssl',
            'port' => 465,
        ]);

        $this->applyBridge();

        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
    }

    public function test_sem_servidor_padrao_a_config_do_mailer_nao_e_alterada(): void
    {
        config(['mail.default' => 'resend']);

        $this->applyBridge();

        $this->assertSame('resend', config('mail.default'), 'Sem servidor cadastrado deve manter o mailer do .env.');
    }

    public function test_senha_ilegivel_nao_derruba_a_aplicacao(): void
    {
        config(['mail.default' => 'log']);

        $server = EmailServer::factory()->create([
            'is_default' => true,
            'active' => true,
            'password' => 'segredo-1234ABCD',
        ]);

        DB::table('email_servers')->where('id', $server->id)->update([
            'password' => 'eyJpdiI6IlVXYXAiLCJ2YWx1ZSI6IngiLCJtYWMiOiJ4In0=',
        ]);

        $this->applyBridge();

        $this->assertSame('log', config('mail.default'));
    }

    public function test_servidor_padrao_inativo_nao_sobrescreve_o_mailer(): void
    {
        config(['mail.default' => 'resend']);
        EmailServer::factory()->inactive()->create(['is_default' => true]);

        $this->applyBridge();

        $this->assertSame('resend', config('mail.default'), 'Servidor padrão inativo não pode assumir o envio.');
    }
}
