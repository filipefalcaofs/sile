<?php

namespace Tests\Feature\Viabilidade;

use App\Models\Parameter;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    use RefreshDatabase;

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

    public function test_pagina_reflete_toggle_desligado(): void
    {
        // HU-014: com features.consulta_viabilidade desligado, a página reflete o
        // toggle (consultaEnabled false) para degradar de forma comunicada na UI.
        $this->seed(ParameterSeeder::class);
        Parameter::query()
            ->where('key', 'features.consulta_viabilidade')
            ->first()
            ->update(['value' => '0']);

        $this->get('/portal/viabilidade')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/viabilidade/consulta')
                ->where('consultaEnabled', false)
            );
    }
}
