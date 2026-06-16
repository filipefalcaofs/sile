<?php

namespace Tests\Feature\Email;

use App\Models\EmailServer;
use App\Providers\MailConfigServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailConfigBridgeTest extends TestCase
{
    use RefreshDatabase;

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
        $this->assertSame('no-reply@sedur.test', config('mail.from.address'));
        $this->assertSame('SEDUR Salvador', config('mail.from.name'));
    }

    public function test_sem_servidor_padrao_a_config_do_mailer_nao_e_alterada(): void
    {
        config(['mail.default' => 'resend']);

        $this->applyBridge();

        $this->assertSame('resend', config('mail.default'), 'Sem servidor cadastrado deve manter o mailer do .env.');
    }

    public function test_servidor_padrao_inativo_nao_sobrescreve_o_mailer(): void
    {
        config(['mail.default' => 'resend']);
        EmailServer::factory()->inactive()->create(['is_default' => true]);

        $this->applyBridge();

        $this->assertSame('resend', config('mail.default'), 'Servidor padrão inativo não pode assumir o envio.');
    }
}
