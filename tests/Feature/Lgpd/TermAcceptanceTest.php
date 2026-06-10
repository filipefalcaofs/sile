<?php

namespace Tests\Feature\Lgpd;

use App\Models\LegalTerm;
use App\Models\LegalTermAcceptance;
use App\Models\User;
use Database\Seeders\LegalTermSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TermAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Arranjo dos testes HTTP do fluxo de aceite (os testes de domínio
     * controlam os próprios termos e não usam seed).
     */
    private function seedRolesAndTerm(): void
    {
        $this->seed([
            RolesAndPermissionsSeeder::class,
            LegalTermSeeder::class,
        ]);
    }

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

    public function test_usuario_sem_aceite_e_redirecionado_ao_termo(): void
    {
        $this->seedRolesAndTerm();
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)
            ->get('/portal/painel')
            ->assertRedirect(route('portal.termo-lgpd.show'));
    }

    public function test_pagina_do_termo_renderiza_conteudo_vigente(): void
    {
        $this->seedRolesAndTerm();
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)
            ->get('/portal/termo-lgpd')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/termo-lgpd')
                ->has('term.title')
                ->has('term.content')
                ->where('term.version', 1));
    }

    public function test_aceite_grava_versao_ip_e_audita(): void
    {
        $this->seedRolesAndTerm();
        $cidadao = User::factory()->cidadao()->create();
        $term = LegalTerm::current('lgpd');

        $this->actingAs($cidadao)
            ->post('/portal/termo-lgpd', ['accepted' => true])
            ->assertRedirect('/portal/painel');

        $this->assertDatabaseHas('legal_term_acceptances', [
            'user_id' => $cidadao->id,
            'legal_term_id' => $term->id,
            'ip_address' => '127.0.0.1',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new LegalTermAcceptance)->getMorphClass(),
            'event' => 'created',
        ]);

        $this->actingAs($cidadao)->get('/portal/painel')->assertOk();
    }

    public function test_aceite_sem_concordancia_e_bloqueado(): void
    {
        $this->seedRolesAndTerm();
        $cidadao = User::factory()->cidadao()->create();

        $this->actingAs($cidadao)
            ->post('/portal/termo-lgpd', ['accepted' => false])
            ->assertSessionHasErrors('accepted');

        $this->assertDatabaseCount('legal_term_acceptances', 0);
    }

    public function test_nova_versao_publicada_reexige_aceite(): void
    {
        $this->seedRolesAndTerm();
        $cidadao = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($cidadao)->get('/portal/painel')->assertOk();

        LegalTerm::factory()->published()->create(['type' => 'lgpd', 'version' => 2]);

        $this->actingAs($cidadao)
            ->get('/portal/painel')
            ->assertRedirect(route('portal.termo-lgpd.show'));
    }

    public function test_visitante_nao_acessa_o_termo(): void
    {
        $this->seedRolesAndTerm();

        $this->get('/portal/termo-lgpd')->assertRedirect('/portal/login');
    }

    public function test_gestao_tambem_exige_termo(): void
    {
        $this->seedRolesAndTerm();

        $administrador = User::factory()->administrador()->create();
        $this->actingAs($administrador)
            ->get('/gestao')
            ->assertRedirect(route('portal.termo-lgpd.show'));

        $cidadao = User::factory()->cidadao()->create();
        $this->actingAs($cidadao)->get('/gestao')->assertForbidden();
    }
}
