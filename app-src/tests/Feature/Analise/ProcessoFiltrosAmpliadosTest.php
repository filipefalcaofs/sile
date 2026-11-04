<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
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
use App\Models\ViabilityRequestEvento;
use App\Services\Servicos\ServicoNomenclaturaResolver;
use App\Models\ViabilityServiceType;

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
        $requerente = Cidadao::factory()->create(['cpf' => '52998224725']);
        $alvo = $this->processo(['requester_cidadao_id' => $requerente->id]);
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

    public function test_viabilidade_locacional_sai_do_filtro_e_entra_em_viabilidade_de_localizacao(): void
    {
        $locacional = ViabilityServiceType::factory()->create(['code' => 'tvl', 'name' => 'Viabilidade locacional']);
        $doPortal = $this->processo(['service_type_id' => $locacional->id]);
        $daJuceb = $this->processo();
        ViabilityRequestEvento::factory()->create([
            'viability_request_id' => $daJuceb->id,
            'codigo' => '101',
            'nome' => 'Inscrição de primeiro estabelecimento',
        ]);
        $outro = $this->processo(['service_type_id' => ViabilityServiceType::factory()->create()->id]);

        $rotulos = array_column(app(ServicoNomenclaturaResolver::class)->opcoesDoFiltro(), 'label');

        $this->assertNotContains('Viabilidade locacional', $rotulos);
        $this->assertContains('Viabilidade de Localização', $rotulos);
        $this->assertEqualsCanonicalizing(
            [$doPortal->id, $daJuceb->id],
            $this->idsFiltrados(['servico' => ProcessoQueryService::PREFIXO_NOME_SERVICO.'viabilidade de localizacao']),
        );
        $this->assertNotContains($outro->id, $this->idsFiltrados(['servico' => ProcessoQueryService::PREFIXO_NOME_SERVICO.'viabilidade de localizacao']));
    }

    public function test_viabilidade_de_localizacao_aparece_no_filtro_mesmo_sem_evento_recebido(): void
    {
        ViabilityServiceType::factory()->create(['code' => 'tvl', 'name' => 'Viabilidade locacional']);

        $rotulos = array_column(app(ServicoNomenclaturaResolver::class)->opcoesDoFiltro(), 'label');

        $this->assertSame(['Viabilidade de Localização'], array_values(array_filter($rotulos, fn (string $r): bool => str_starts_with($r, 'Viabilidade'))));
    }

    public function test_filtra_pelo_numero_da_porta_sem_zeros_a_esquerda(): void
    {
        $sete = $this->processo(['address_number' => '000007']);
        $this->processo(['address_number' => '17']);
        $this->processo(['address_number' => '70']);

        $this->assertSame([$sete->id], $this->idsFiltrados(['numero' => '7']));
        $this->assertSame([$sete->id], $this->idsFiltrados(['numero' => ' 07 ']));
    }

    public function test_numero_da_porta_e_filtro_das_caixas(): void
    {
        $this->assertContains('numero', ProcessoQueryService::CHAVES_FILTRO_CAIXA);
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
