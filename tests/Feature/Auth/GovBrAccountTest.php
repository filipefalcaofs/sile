<?php

namespace Tests\Feature\Auth;

use App\Models\GovBrAccount;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovBrAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_vinculo_govbr_pertence_ao_usuario_com_metadados_tipados(): void
    {
        $account = GovBrAccount::factory()->create([
            'reliability_level' => 'prata',
            'reliability_levels' => [1, 2],
        ]);

        $user = $account->user;

        $this->assertInstanceOf(User::class, $user);
        $this->assertTrue($user->govBrAccount->is($account));
        $this->assertSame('prata', $user->govBrAccount->reliability_level);
        $this->assertSame([1, 2], $user->govBrAccount->reliability_levels);
        $this->assertNotNull($account->linked_at);
    }

    public function test_usuario_tem_no_maximo_um_vinculo_govbr(): void
    {
        $account = GovBrAccount::factory()->create();

        $this->expectException(QueryException::class);

        GovBrAccount::factory()->create(['user_id' => $account->user_id]);
    }
}
