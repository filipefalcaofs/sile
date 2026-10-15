<?php

namespace Tests\Feature\Cidadao;

use App\Models\Cidadao;
use App\Services\GovBr\GovBrAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class GovBrSenhaCidadaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function garantirSenha(Cidadao $cidadao): void
    {
        $metodo = new ReflectionMethod(GovBrAuthService::class, 'garantirSenha');
        $metodo->invoke(app(GovBrAuthService::class), $cidadao);
    }

    public function test_registro_de_balcao_recebe_senha_aleatoria_ao_entrar_por_govbr(): void
    {
        $balcao = Cidadao::factory()->semSenha()->create();

        $this->garantirSenha($balcao);

        $this->assertTrue($balcao->fresh()->temSenha());
    }

    public function test_cidadao_com_senha_mantem_a_senha(): void
    {
        $cidadao = Cidadao::factory()->create();
        $hash = $cidadao->password;

        $this->garantirSenha($cidadao);

        $this->assertSame($hash, $cidadao->fresh()->password);
    }
}
