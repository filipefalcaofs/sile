<?php

namespace Tests\Feature\Viabilidade;

use App\Models\Cidadao;
use App\Models\Procuration;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Página PÚBLICA da consulta de viabilidade (HU-054/055/056/057/058/059): agora
 * que `portal/viabilidade/consulta.tsx` existe em disco (07-08), o teste asserta
 * o componente Inertia renderizado e o reflexo honesto do toggle
 * `features.consulta_viabilidade` na prop `consultaEnabled` — o que não era
 * possível no 07-06 (assertInertia()->component() exige o .tsx).
 */
class ConsultaViabilidadePaginaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_pagina_publica_renderiza_componente_de_consulta(): void
    {
        // Cidadão ANÔNIMO (sem auth:web) acessa a página: renderiza o componente
        // público da consulta com a prop de toggle — sem login.
        $this->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/viabilidade/consulta')
                ->has('consultaEnabled')
            );
    }

    public function test_pagina_continua_acessivel_para_cidadao_logado(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = Cidadao::factory()->withAcceptedLgpdTerm()->create();

        $this->actingAs($user)
            ->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/viabilidade/consulta')
                ->where('auth.user.id', $user->id));
    }

    public function test_consulta_logada_mantem_a_representacao_ativa(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $grantor = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $attorney = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procuration = Procuration::factory()->create([
            'grantor_cidadao_id' => $grantor->id,
            'attorney_cidadao_id' => $attorney->id,
        ]);

        $this->actingAs($attorney)
            ->withSession(['acting_procuration_id' => $procuration->id])
            ->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/viabilidade/consulta')
                ->where('actingFor.id', $grantor->id));
    }

    public function test_anonimo_com_representacao_velha_na_sessao_segue_publico(): void
    {
        // Procuração real na sessão: sem usuário logado, a revalidação tem de
        // encerrar a representação em vez de quebrar ao ler o id do usuário.
        $procuration = Procuration::factory()->create();

        $this->withSession(['acting_procuration_id' => $procuration->id])
            ->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/viabilidade/consulta')
                ->where('actingFor', null));
    }
}
