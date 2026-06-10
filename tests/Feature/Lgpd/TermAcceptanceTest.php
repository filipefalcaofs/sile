<?php

namespace Tests\Feature\Lgpd;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Database\Seeders\LegalTermSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_retorna_versao_publicada_mais_recente(): void
    {
        LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);
        LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 2]);
        LegalTerm::factory()->create(['type' => 'lgpd', 'version' => 3]);

        $this->assertSame(2, LegalTerm::current('lgpd')->version);
    }

    public function test_current_retorna_null_sem_termo_publicado(): void
    {
        $this->assertNull(LegalTerm::current('lgpd'));
    }

    public function test_usuario_sabe_se_aceitou_o_termo(): void
    {
        $user = User::factory()->create();
        $term = LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 1]);

        $this->assertFalse($user->hasAcceptedTerm($term));

        LegalTermAcceptance::create([
            'user_id' => $user->id,
            'legal_term_id' => $term->id,
            'accepted_at' => now(),
        ]);

        $this->assertTrue($user->hasAcceptedTerm($term));
    }

    public function test_seeder_publica_versao_1_do_termo(): void
    {
        $this->seed(LegalTermSeeder::class);

        $term = LegalTerm::current('lgpd');

        $this->assertNotNull($term);
        $this->assertSame(1, $term->version);
        $this->assertNotEmpty($term->content);
    }
}
