<?php

namespace Tests\Feature\Auth;

use App\Models\Sector;
use App\Models\User;
use App\Models\UserSectorAssignment;
use App\Services\Auth\UserSectorAssignmentSync;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sincronização usuário×setor a partir do discovery AD: cria setor se
 * necessário, atualiza o vínculo atual e grava histórico com vigência.
 */
class UserSectorAssignmentSyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_vincula_usuario_ao_setor_existente_e_abre_historico(): void
    {
        $user = User::factory()->create();
        $setor = Sector::factory()->create([
            'code' => '0231400300',
            'name' => 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
        ]);

        app(UserSectorAssignmentSync::class)->syncFromDiscovery(
            $user,
            '0231400300',
            'SUCOM-GERAE-STD-Setor de Triagem e Distribuição',
        );

        $this->assertTrue($user->sectors()->whereKey($setor->id)->exists());
        $this->assertDatabaseHas('user_sector_assignments', [
            'user_id' => $user->id,
            'sector_id' => $setor->id,
            'source' => 'discovery',
            'ended_at' => null,
        ]);
    }

    public function test_cria_setor_quando_discovery_indica_codigo_inexistente(): void
    {
        $user = User::factory()->create();

        app(UserSectorAssignmentSync::class)->syncFromDiscovery(
            $user,
            '9999999999',
            'Setor Novo Via Discovery',
        );

        $setor = Sector::query()->where('code', '9999999999')->first();
        $this->assertNotNull($setor);
        $this->assertSame('Setor Novo Via Discovery', $setor->name);
        $this->assertTrue($setor->active);
        $this->assertTrue($user->sectors()->whereKey($setor->id)->exists());
    }

    public function test_troca_de_setor_encerra_vigencia_anterior_e_abre_nova(): void
    {
        $user = User::factory()->create();
        $anterior = Sector::factory()->create(['code' => '1111111111', 'name' => 'Setor Anterior']);
        $novo = Sector::factory()->create(['code' => '2222222222', 'name' => 'Setor Novo']);

        $user->sectors()->attach($anterior);
        UserSectorAssignment::query()->create([
            'user_id' => $user->id,
            'sector_id' => $anterior->id,
            'source' => 'discovery',
            'started_at' => now()->subDay(),
            'ended_at' => null,
            'previous_sector_id' => null,
        ]);

        app(UserSectorAssignmentSync::class)->syncFromDiscovery($user, '2222222222', 'Setor Novo');

        $this->assertFalse($user->sectors()->whereKey($anterior->id)->exists());
        $this->assertTrue($user->sectors()->whereKey($novo->id)->exists());

        $this->assertDatabaseHas('user_sector_assignments', [
            'user_id' => $user->id,
            'sector_id' => $anterior->id,
            'source' => 'discovery',
        ]);
        $this->assertNotNull(
            UserSectorAssignment::query()
                ->where('user_id', $user->id)
                ->where('sector_id', $anterior->id)
                ->value('ended_at'),
        );

        $atual = UserSectorAssignment::query()
            ->where('user_id', $user->id)
            ->where('sector_id', $novo->id)
            ->whereNull('ended_at')
            ->first();

        $this->assertNotNull($atual);
        $this->assertSame($anterior->id, $atual->previous_sector_id);
        $this->assertSame('discovery', $atual->source);
    }

    public function test_mesmo_setor_nao_duplica_historico_nem_pivot(): void
    {
        $user = User::factory()->create();
        $setor = Sector::factory()->create(['code' => '0231400300', 'name' => 'Triagem']);

        $sync = app(UserSectorAssignmentSync::class);
        $sync->syncFromDiscovery($user, '0231400300', 'Triagem');
        $sync->syncFromDiscovery($user, '0231400300', 'Triagem');

        $this->assertSame(1, $user->sectors()->count());
        $this->assertSame(
            1,
            UserSectorAssignment::query()->where('user_id', $user->id)->count(),
        );
        $this->assertSame(
            1,
            DB::table('sector_user')->where('user_id', $user->id)->where('sector_id', $setor->id)->count(),
        );
    }

    public function test_sem_codigo_nem_nome_nao_altera_vinculos(): void
    {
        $user = User::factory()->create();
        $setor = Sector::factory()->create();
        $user->sectors()->attach($setor);

        app(UserSectorAssignmentSync::class)->syncFromDiscovery($user, null, null);

        $this->assertSame(1, $user->sectors()->count());
        $this->assertSame(0, UserSectorAssignment::query()->count());
    }

    public function test_auditoria_registra_troca_de_setor_sem_token_ou_senha(): void
    {
        $user = User::factory()->create();
        $anterior = Sector::factory()->create(['code' => '1111111111', 'name' => 'A']);
        $novo = Sector::factory()->create(['code' => '2222222222', 'name' => 'B']);
        $user->sectors()->attach($anterior);
        UserSectorAssignment::query()->create([
            'user_id' => $user->id,
            'sector_id' => $anterior->id,
            'source' => 'discovery',
            'started_at' => now()->subDay(),
        ]);

        app(UserSectorAssignmentSync::class)->syncFromDiscovery($user, '2222222222', 'B');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'setores',
            'event' => 'sincronizar-lotacao-discovery',
        ]);

        $props = DB::table('activity_log')
            ->where('event', 'sincronizar-lotacao-discovery')
            ->value('properties');

        $this->assertStringNotContainsString('Bearer', (string) $props);
        $this->assertStringNotContainsString('token', strtolower((string) $props));
        $this->assertStringNotContainsString('senha', strtolower((string) $props));
    }
}
