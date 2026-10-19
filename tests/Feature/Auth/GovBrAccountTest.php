<?php

namespace Tests\Feature\Auth;

use App\Models\Cidadao;
use App\Models\GovBrAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GovBrAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vinculo_govbr_pertence_ao_usuario_com_metadados_tipados(): void
    {
        $account = GovBrAccount::factory()->create([
            'reliability_level' => 'prata',
            'reliability_levels' => [1, 2],
        ]);

        $user = $account->cidadao;

        $this->assertInstanceOf(Cidadao::class, $user);
        $this->assertTrue($user->govBrAccount->is($account));
        $this->assertSame('prata', $user->govBrAccount->reliability_level);
        $this->assertSame([1, 2], $user->govBrAccount->reliability_levels);
        $this->assertNotNull($account->linked_at);
    }

    public function test_usuario_tem_no_maximo_um_vinculo_govbr(): void
    {
        $account = GovBrAccount::factory()->create();

        $this->expectException(QueryException::class);

        GovBrAccount::factory()->create(['cidadao_id' => $account->cidadao_id]);
    }
}
