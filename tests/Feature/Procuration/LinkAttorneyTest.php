<?php

namespace Tests\Feature\Procuration;

use App\Models\Procuration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LinkAttorneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Cidadão habilitado a navegar no portal (papel + termo LGPD aceito).
     */
    private function portalUser(): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create();
    }

    public function test_pagina_de_procuracoes_renderiza(): void
    {
        $grantor = $this->portalUser();

        $this->actingAs($grantor)
            ->get('/portal/procuracoes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/procuracoes/index')
                ->has('granted')
                ->has('received'));
    }

    public function test_vinculo_de_procurador_criado_com_sucesso(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => null,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseHas('procurations', [
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
            'revoked_at' => null,
        ]);
    }

    public function test_vinculo_com_vigencia_definida(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => now()->addMonths(6)->format('Y-m-d'),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $procuration = Procuration::query()
            ->where('grantor_user_id', $grantor->id)
            ->where('attorney_user_id', $attorney->id)
            ->first();

        $this->assertNotNull($procuration);
        $this->assertNotNull($procuration->expires_at);
        $this->assertTrue($procuration->expires_at->isFuture());
    }

    public function test_vinculo_gera_auditoria(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        $this->actingAs($grantor)->post('/portal/procuracoes', [
            'attorney_email' => $attorney->email,
            'expires_at' => null,
        ]);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => (new Procuration)->getMorphClass(),
            'event' => 'created',
            'result' => 'sucesso',
        ]);
    }

    public function test_email_inexistente_bloqueia_com_orientacao(): void
    {
        $grantor = $this->portalUser();

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => 'nao-cadastrado@example.com',
                'expires_at' => null,
            ])
            ->assertSessionHasErrors('attorney_email');

        $this->assertStringContainsString(
            'cadastr',
            session('errors')->first('attorney_email'),
        );
        $this->assertDatabaseCount('procurations', 0);
    }

    public function test_auto_procuracao_bloqueada(): void
    {
        $grantor = $this->portalUser();

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => $grantor->email,
                'expires_at' => null,
            ])
            ->assertSessionHasErrors('attorney_email');

        $this->assertDatabaseCount('procurations', 0);
    }

    public function test_duplicidade_ativa_bloqueada(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        Procuration::factory()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => null,
            ])
            ->assertSessionHasErrors('attorney_email');

        $this->assertDatabaseCount('procurations', 1);
    }

    public function test_procuracao_revogada_permite_novo_vinculo(): void
    {
        $grantor = $this->portalUser();
        $attorney = $this->portalUser();

        Procuration::factory()->revoked()->create([
            'grantor_user_id' => $grantor->id,
            'attorney_user_id' => $attorney->id,
        ]);

        $this->actingAs($grantor)
            ->post('/portal/procuracoes', [
                'attorney_email' => $attorney->email,
                'expires_at' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('procurations', 2);
    }

    public function test_visitante_nao_acessa_procuracoes(): void
    {
        $this->get('/portal/procuracoes')->assertRedirect('/login');
    }
}
