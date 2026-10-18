<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\ProcessoQueryService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Filtros da consulta de processos e das caixas pedidos pela SEDUR: CNAE,
 * complemento do endereço, CPF ou CNPJ no mesmo campo e a categoria de abrigado
 * de escritório virtual.
 */
class ProcessoFiltrosAmpliadosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function processo(array $attrs = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT),
            'protocoled_at' => now(),
        ], $attrs));
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return list<int>
     */
    private function idsFiltrados(array $filtros): array
    {
        return app(ProcessoQueryService::class)
            ->aplicarFiltros(ViabilityRequest::query(), $filtros)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function test_filtra_por_codigo_do_cnae_com_ou_sem_pontuacao(): void
    {
        $cnae = Cnae::factory()->create(['code' => '8211300', 'description' => 'Serviços combinados de escritório']);
        $alvo = $this->processo();
        $alvo->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $this->processo();

        $this->assertSame([$alvo->id], $this->idsFiltrados(['cnae' => '8211-3/00']));
        $this->assertSame([$alvo->id], $this->idsFiltrados(['cnae' => '8211300']));
    }

    public function test_filtra_por_parte_da_descricao_do_cnae(): void
    {
        $cnae = Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes e similares']);
        $alvo = $this->processo();
        $alvo->cnaes()->attach($cnae->id, ['is_primary' => true]);
        $this->processo();

        $this->assertSame([$alvo->id], $this->idsFiltrados(['cnae' => 'restaurantes']));
    }

    public function test_filtra_por_complemento_do_endereco(): void
    {
        $alvo = $this->processo(['address_complement' => 'Sala 204 Edifício Atlântico']);
        $this->processo(['address_complement' => 'Loja 3']);

        $this->assertSame([$alvo->id], $this->idsFiltrados(['complemento' => 'sala 204']));
    }

    public function test_campo_de_documento_encontra_cnpj_mesmo_com_mascara(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '11222333000181']);
        $alvo = $this->processo(['company_id' => $empresa->id]);
        $this->processo();

        $this->assertSame([$alvo->id], $this->idsFiltrados(['cnpj' => '11.222.333/0001-81']));
    }

    public function test_campo_de_documento_encontra_cpf_do_requerente(): void
    {
        $requerente = User::factory()->create(['cpf' => '52998224725']);
        $alvo = $this->processo(['requester_user_id' => $requerente->id]);
        $this->processo();

        $this->assertSame([$alvo->id], $this->idsFiltrados(['cnpj' => '529.982.247-25']));
    }

    public function test_documento_so_com_pontuacao_nao_devolve_tudo(): void
    {
        $this->processo();

        $this->assertSame([], $this->idsFiltrados(['cnpj' => '../-']));
    }

    public function test_categoria_abrigado_de_virtual_pega_intencao_e_decisao(): void
    {
        $pelaIntencao = $this->processo(['wants_virtual_office_tenant' => true]);
        $pelaDecisao = $this->processo();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $pelaDecisao->id,
            'is_virtual_office_tenant' => true,
        ]);
        $this->processo();

        $ids = $this->idsFiltrados(['categoria' => 'abrigado_escritorio']);

        sort($ids);
        $esperado = [$pelaIntencao->id, $pelaDecisao->id];
        sort($esperado);

        $this->assertSame($esperado, $ids);
    }

    public function test_abrigado_aparece_nas_categorias_do_processo(): void
    {
        $processo = $this->processo(['wants_virtual_office_tenant' => true]);

        $valores = array_column(ProcessoQueryService::categoriasDe($processo), 'value');

        $this->assertContains('abrigado_escritorio', $valores);
    }

    public function test_consulta_de_processos_aceita_os_filtros_novos_na_url(): void
    {
        $alvo = $this->processo(['address_complement' => 'Sala 9']);
        $this->processo(['address_complement' => 'Loja 1']);
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/processos?complemento=sala')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $alvo->id));
    }
}
