<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A tela de Resultado do fluxo expresso aceita os mesmos filtros da Consulta de
 * processos, aplicados às solicitações decididas.
 */
class ResultadoExpressoFiltrosConsultaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function decidida(array $atributos = []): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::Deferida,
            'protocol_number' => 'VIA-2026-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ], $atributos));
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-'.str_pad((string) $this->seq, 7, '0', STR_PAD_LEFT),
        ]);

        return $request;
    }

    /**
     * @return list<int>
     */
    private function idsDaLista(string $query): array
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $ids = [];

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/resultados-expresso?'.$query)
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids): void {
                $ids = collect($page->toArray()['props']['decisoes']['data'])->pluck('id')->all();
            });

        return $ids;
    }

    public function test_filtra_por_bap_e_complemento(): void
    {
        $alvo = $this->decidida(['external_reference' => 'BAP2601000999', 'address_complement' => 'Sala 7']);
        $this->decidida(['external_reference' => 'BAP2601000111', 'address_complement' => 'Loja 1']);

        $this->assertSame([$alvo->id], $this->idsDaLista('bap=000999'));
        $this->assertSame([$alvo->id], $this->idsDaLista('complemento=sala'));
    }

    public function test_filtra_por_cnae_e_por_cpf_ou_cnpj_no_mesmo_campo(): void
    {
        $cnae = Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes']);
        $empresa = Company::factory()->create(['cnpj' => '11222333000181']);
        $alvo = $this->decidida(['company_id' => $empresa->id]);
        $alvo->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $this->decidida();

        $this->assertSame([$alvo->id], $this->idsDaLista('cnae=5611-2/01'));
        $this->assertSame([$alvo->id], $this->idsDaLista('cnpj='.urlencode('11.222.333/0001-81')));
    }

    public function test_protocolado_de_e_ate_filtram_pela_data_do_protocolo_e_nao_pela_da_decisao(): void
    {
        $antigo = $this->decidida(['protocoled_at' => now()->subDays(30)]);
        $recente = $this->decidida(['protocoled_at' => now()->subDay()]);
        $limite = now()->subDays(5)->toDateString();

        $this->assertSame([$recente->id], $this->idsDaLista('protocolado_de='.$limite));
        $this->assertSame([$antigo->id], $this->idsDaLista('protocolado_ate='.$limite));
    }

    public function test_filtros_novos_voltam_nos_props_da_pagina(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/resultados-expresso?bap=X&cnae=8211&protocolado_de=2026-01-01')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filtros.bap', 'X')
                ->where('filtros.cnae', '8211')
                ->where('filtros.protocolado_de', '2026-01-01')
                ->has('servicoOptions')
                ->has('categoriaOptions')
                ->has('analistaOptions')
                ->has('statusOptions'));
    }
}
