<?php

namespace Tests\Feature\Email;

use App\Models\EmailServer;
use Database\Seeders\EmailServerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailServerSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_semeia_o_smtp_zoho_da_sudoeste_como_padrao_ativo(): void
    {
        $this->seed(EmailServerSeeder::class);

        $server = EmailServer::query()->where('host', 'smtp.zoho.com')->first();

        $this->assertNotNull($server);
        $this->assertSame('Zoho Mail (Sudoeste)', $server->name);
        $this->assertSame('smtp', $server->driver);
        $this->assertSame(587, $server->port);
        $this->assertSame('tls', $server->encryption);
        $this->assertSame(30, $server->timeout);
        $this->assertSame('atendimento2@sudoesteinformatica.com.br', $server->username);
        $this->assertSame('atendimento2@sudoesteinformatica.com.br', $server->from_address);
        $this->assertSame('Viabiliza', $server->from_name);
        $this->assertTrue($server->active);
        $this->assertTrue($server->is_default);
        $this->assertSame(EmailServerSeeder::PASSWORD, $server->password);
        $this->assertNotSame(
            EmailServerSeeder::PASSWORD,
            DB::table('email_servers')->where('id', $server->id)->value('password'),
        );
    }

    public function test_e_idempotente_e_preserva_senha_alterada_na_tela(): void
    {
        $this->seed(EmailServerSeeder::class);

        EmailServer::query()
            ->where('host', 'smtp.zoho.com')
            ->firstOrFail()
            ->update(['password' => 'senha-rotacionada-na-tela']);

        $this->seed(EmailServerSeeder::class);

        $this->assertSame(1, EmailServer::query()->where('host', 'smtp.zoho.com')->count());
        $this->assertSame(
            'senha-rotacionada-na-tela',
            EmailServer::query()->where('host', 'smtp.zoho.com')->firstOrFail()->password,
        );
    }

    public function test_nao_quebra_quando_a_senha_gravada_nao_abre_com_a_chave_atual(): void
    {
        $this->seed(EmailServerSeeder::class);

        $id = EmailServer::query()->where('host', EmailServerSeeder::HOST)->value('id');
        DB::table('email_servers')->where('id', $id)->update([
            'password' => 'texto-cifrado-com-outra-chave',
        ]);

        $this->seed(EmailServerSeeder::class);

        $this->assertSame(
            'texto-cifrado-com-outra-chave',
            DB::table('email_servers')->where('id', $id)->value('password'),
        );
    }

    public function test_passa_a_ser_o_unico_servidor_padrao(): void
    {
        $anterior = EmailServer::factory()->default()->create(['host' => 'smtp.outro.test']);

        $this->seed(EmailServerSeeder::class);

        $this->assertFalse($anterior->fresh()->is_default);
        $this->assertTrue(
            EmailServer::query()->where('host', 'smtp.zoho.com')->firstOrFail()->is_default,
        );
    }
}
