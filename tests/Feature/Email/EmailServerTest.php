<?php

namespace Tests\Feature\Email;

use App\Models\EmailServer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmailServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_senha_e_gravada_criptografada_e_lida_em_claro_pelo_model(): void
    {
        $server = EmailServer::factory()->create(['password' => 'segredo-1234ABCD']);

        $rawNoBanco = DB::table('email_servers')->where('id', $server->id)->value('password');

        $this->assertNotSame('segredo-1234ABCD', $rawNoBanco, 'A senha não pode ser gravada em texto claro.');
        $this->assertSame('segredo-1234ABCD', Crypt::decryptString($rawNoBanco));
        $this->assertSame('segredo-1234ABCD', $server->fresh()->password);
    }

    public function test_a_senha_mascarada_nunca_expoe_o_miolo_da_credencial(): void
    {
        $server = EmailServer::factory()->create(['password' => 'segredo-1234ABCD']);

        $masked = $server->masked_password;

        $this->assertStringNotContainsString('1234', $masked);
        $this->assertStringStartsWith('segr', $masked);
        $this->assertStringEndsWith('ABCD', $masked);
    }

    public function test_a_senha_mascarada_de_credencial_vazia_e_nula(): void
    {
        $server = EmailServer::factory()->create(['password' => null]);

        $this->assertNull($server->masked_password);
    }

    public function test_definir_como_padrao_desmarca_o_padrao_anterior(): void
    {
        $primeiro = EmailServer::factory()->create(['is_default' => true]);
        $segundo = EmailServer::factory()->create(['is_default' => false]);

        $segundo->setAsDefault();

        $this->assertFalse($primeiro->fresh()->is_default);
        $this->assertTrue($segundo->fresh()->is_default);
    }
}
