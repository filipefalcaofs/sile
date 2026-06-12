<?php

namespace Tests\Feature\Settings;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\VerifyEmailQueued;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_pagina_de_perfil_renderiza_com_dados(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)
            ->get('/settings/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('user.name', $user->name)
                ->where('user.email', $user->email)
                ->where('user.cpf', $user->cpf)
                ->where('user.phone', $user->phone));
    }

    public function test_atualiza_nome_e_telefone(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Nome Novo',
            'email' => $user->email,
            'phone' => '(71) 98888-7777',
        ]);

        $response->assertSessionHasNoErrors()->assertRedirect();

        $user->refresh();

        $this->assertSame('Nome Novo', $user->name);
        $this->assertSame('(71) 98888-7777', $user->phone);
    }

    public function test_atualizacao_gera_auditoria_sem_campos_sensiveis(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => 'Nome Auditado',
            'email' => $user->email,
            'phone' => $user->phone,
        ]);

        $activity = Activity::query()
            ->where('event', 'updated')
            ->where('subject_type', User::class)
            ->where('subject_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'Esperava activity de atualização do perfil');

        $changedAttributes = array_keys($activity->attribute_changes['attributes'] ?? []);

        $this->assertContains('name', $changedAttributes);

        $serialized = json_encode([$activity->attribute_changes, $activity->properties]);

        $this->assertStringNotContainsString('password', $serialized);
    }

    public function test_alterar_email_reexige_verificacao(): void
    {
        Notification::fake();

        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => 'novo-email@example.com',
            'phone' => $user->phone,
        ]);

        $response->assertSessionHasNoErrors();

        $user = $user->fresh();

        $this->assertSame('novo-email@example.com', $user->email);
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailQueued::class);
    }

    public function test_cpf_nao_e_alteravel(): void
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create(['cpf' => '11144477735']);

        $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'cpf' => '52998224725',
        ]);

        $this->assertSame('11144477735', $user->fresh()->cpf);
    }

    public function test_email_duplicado_bloqueia(): void
    {
        $other = User::factory()->create();
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $response = $this->actingAs($user)->patch('/settings/profile', [
            'name' => $user->name,
            'email' => $other->email,
            'phone' => $user->phone,
        ]);

        $response->assertSessionHasErrors('email');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_visitante_nao_acessa_perfil(): void
    {
        $this->get('/settings/profile')->assertRedirect('/portal/login');
    }
}
