<?php

namespace Tests\Feature\Renovacao;

use App\Enums\CompanySource;
use App\Enums\TvlKind;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ServiceDeclaration;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityServiceType;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegada;
use App\Services\Legado\TvlLegadaDetalhe;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Renovação sem vínculo de empresa: qualquer cidadão busca por CNPJ ou número,
 * no Viabiliza e no sistema anterior, e quem protocola vira requerente.
 */
class RenovacaoBuscaTest extends TestCase
{
    use FakeCadastroImobiliario;
    use LazilyRefreshDatabase;

    private const CNPJ = '11222333000181';

    private const RAZAO = 'PADARIA ESTRELA LTDA';

    private const CODIGO_CNAE = '4721102';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Cnae::factory()->create(['code' => self::CODIGO_CNAE]);
        $this->cnpjForaDoAr();
    }

    public function test_pagina_sem_busca_mostra_so_o_campo(): void
    {
        $this->actingAs($this->cidadao())->get('/portal/renovacao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/index')
                ->where('busca', null)
                ->has('itens', 0)
                ->where('indisponivel', false));
    }

    public function test_busca_por_cnpj_junta_viabiliza_e_sistema_anterior(): void
    {
        $local = $this->tvlLocal('TVL-2026-000777');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('provisoriasPorCnpj')->with(self::CNPJ)->andReturn([$this->resumo(1111111)]);
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
        });

        $this->actingAs($this->cidadao())->get('/portal/renovacao?busca=11.222.333/0001-81')
            ->assertInertia(fn (Assert $page) => $page
                ->has('itens', 2)
                ->where('itens.0.origem', 'viabiliza')
                ->where('itens.0.numero', 'TVL-2026-000777')
                ->where('itens.0.url', '/portal/renovacao/'.$local->id)
                ->where('itens.1.origem', 'legado')
                ->where('itens.1.numero', '1111111')
                ->where('itens.1.elegivel', true)
                ->where('itens.1.url', '/portal/renovacao/legado/'.self::CNPJ.'/1111111'));
    }

    public function test_busca_por_numero_acha_tvl_do_viabiliza_e_do_legado(): void
    {
        $this->tvlLocal('2104040');
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(2104040)->andReturn($this->detalhe(2104040));
        });

        $this->actingAs($this->cidadao())->get('/portal/renovacao?busca=2104040')
            ->assertInertia(fn (Assert $page) => $page
                ->has('itens', 2)
                ->where('itens.0.origem', 'viabiliza')
                ->where('itens.1.origem', 'legado')
                ->where('itens.1.cnpj', '11.222.333/0001-81'));
    }

    public function test_tvl_legada_sem_cnpj_buscada_por_numero_pode_ser_renovada_sem_cnpj(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(3333333)->andReturn($this->detalhe(3333333, titular: null));
        });

        $this->actingAs($this->cidadao())->get('/portal/renovacao?busca=3333333')
            ->assertInertia(fn (Assert $page) => $page
                ->has('itens', 1)
                ->where('itens.0.elegivel', true)
                ->where('itens.0.cnpj', null)
                ->where('itens.0.url', '/portal/renovacao/legado/3333333'));
    }

    public function test_assistente_abre_para_tvl_legada_sem_cnpj(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(3333333)->andReturn($this->detalhe(3333333, titular: null));
        });

        $this->actingAs($this->cidadao())->get('/portal/renovacao/legado/3333333')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/wizard')
                ->where('origem.legado', true)
                ->where('origem.numero', 3333333)
                ->where('origem.cnpj', null)
                ->where('origem.empresa', self::RAZAO));
    }

    public function test_protocolo_legado_sem_cnpj_cria_empresa_sem_cnpj_com_a_razao_da_tvl(): void
    {
        $this->declaracaoDaRenovacao();
        $cidadao = $this->cidadao();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(3333333)->andReturn($this->detalhe(3333333, titular: null));
        });

        $this->actingAs($cidadao)->post('/portal/renovacao/legado/3333333', $this->formulario())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $renovacao = ViabilityRequest::query()->where('renews_legacy_tvl_number', 3333333)->firstOrFail();
        $this->assertSame($cidadao->id, $renovacao->requester_user_id);
        $this->assertNull($renovacao->company->cnpj);
        $this->assertSame(self::RAZAO, $renovacao->company->legal_name);
        $this->assertSame(CompanySource::Legado, $renovacao->company->source);
    }

    public function test_tvl_legada_sem_cnpj_ja_em_analise_nao_e_renovada_de_novo(): void
    {
        $this->declaracaoDaRenovacao();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(3333333)->andReturn($this->detalhe(3333333, titular: null));
        });

        $this->actingAs($this->cidadao())->post('/portal/renovacao/legado/3333333', $this->formulario())
            ->assertSessionHas('status');
        $this->actingAs($this->cidadao())->post('/portal/renovacao/legado/3333333', $this->formulario())
            ->assertSessionHas('error');

        $this->assertSame(1, ViabilityRequest::query()->where('renews_legacy_tvl_number', 3333333)->count());
    }

    public function test_termo_invalido_avisa_os_formatos(): void
    {
        $this->actingAs($this->cidadao())->get('/portal/renovacao?busca=abc')
            ->assertInertia(fn (Assert $page) => $page
                ->where('termo_invalido', true)
                ->has('itens', 0));
    }

    public function test_qualquer_cidadao_abre_o_assistente_e_contato_e_o_do_usuario(): void
    {
        $cidadao = $this->cidadao(['email' => 'renova@exemplo.com', 'phone' => '71999990000']);
        Company::factory()->create(['cnpj' => self::CNPJ, 'email' => 'contato@padaria.com']);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
        });

        $this->actingAs($cidadao)->get('/portal/renovacao/legado/'.self::CNPJ.'/1111111')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/wizard')
                ->where('origem.legado', true)
                ->where('origem.cnpj', '11.222.333/0001-81')
                ->where('contato.email', 'renova@exemplo.com'));
    }

    public function test_assistente_de_tvl_do_viabiliza_abre_para_qualquer_cidadao(): void
    {
        $local = $this->tvlLocal('TVL-2026-000777');
        $local->company->forceFill(['email' => 'contato@padaria.com'])->save();

        $this->actingAs($this->cidadao(['email' => 'renova@exemplo.com']))->get('/portal/renovacao/'.$local->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portal/renovacao/wizard')
                ->where('contato.email', 'renova@exemplo.com'));
    }

    public function test_protocolo_legado_cria_a_empresa_uma_vez(): void
    {
        $this->declaracaoDaRenovacao();
        $cidadao = $this->cidadao();
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
            $client->shouldReceive('tvlPorNumero')->with(2222222)->andReturn($this->detalhe(2222222));
        });

        foreach ([1111111, 2222222] as $numero) {
            $this->actingAs($cidadao)->post('/portal/renovacao/legado/'.self::CNPJ.'/'.$numero, $this->formulario())
                ->assertSessionHasNoErrors()
                ->assertSessionHas('status');
        }

        $empresas = Company::query()->where('cnpj', self::CNPJ)->get();
        $this->assertCount(1, $empresas);
        $this->assertSame(CompanySource::Legado, $empresas->first()->source);
        $this->assertSame(self::RAZAO, $empresas->first()->legal_name);
        $this->assertSame(2, ViabilityRequest::query()
            ->where('company_id', $empresas->first()->id)
            ->where('requester_user_id', $cidadao->id)
            ->whereNotNull('renews_legacy_tvl_number')
            ->count());
    }

    public function test_protocolo_legado_reaproveita_a_empresa_do_cnpj_sem_trocar_o_contato(): void
    {
        $this->declaracaoDaRenovacao();
        $primeira = Company::factory()->create(['cnpj' => self::CNPJ, 'email' => 'contato@padaria.com']);
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111));
        });

        $this->actingAs($this->cidadao())->post('/portal/renovacao/legado/'.self::CNPJ.'/1111111', $this->formulario())
            ->assertSessionHas('status');

        $renovacao = ViabilityRequest::query()->where('renews_legacy_tvl_number', 1111111)->firstOrFail();
        $this->assertSame($primeira->id, $renovacao->company_id);
        $this->assertSame('contato@padaria.com', $primeira->refresh()->email);
    }

    public function test_tvl_legada_de_outro_cnpj_nao_abre_o_assistente(): void
    {
        $this->mockClient(function (MockInterface $client): void {
            $client->shouldReceive('tvlPorNumero')->with(1111111)->andReturn($this->detalhe(1111111, titular: '99888777000166'));
        });

        $this->actingAs($this->cidadao())->get('/portal/renovacao/legado/'.self::CNPJ.'/1111111')
            ->assertRedirect('/portal/renovacao')
            ->assertSessionHas('error');
    }

    private function cidadao(array $atributos = []): User
    {
        return User::factory()->cidadao()->withAcceptedLgpdTerm()->create($atributos);
    }

    private function tvlLocal(string $numero): ViabilityRequest
    {
        $empresa = Company::factory()->create(['cnpj' => self::CNPJ, 'legal_name' => self::RAZAO]);
        $origem = ViabilityRequest::factory()->withPrimaryCnae()->create(['company_id' => $empresa->id]);
        $origem->forceFill(['status' => ViabilityRequestStatus::Deferida, 'protocol_number' => 'VIA-2025-'.$origem->id, 'protocoled_at' => now()->subYear()])->save();
        ViabilityDecision::factory()->create([
            'viability_request_id' => $origem->id,
            'tvl_kind' => TvlKind::Provisoria,
            'tvl_product_number' => $numero,
            'tvl_valid_until' => now()->addMonths(3)->toDateString(),
            'decided_at' => now()->subMonths(6),
        ]);

        return $origem->refresh();
    }

    private function declaracaoDaRenovacao(): void
    {
        $tipo = ViabilityServiceType::query()->firstOrCreate(
            ['code' => 'renovacao-tvl'],
            ['name' => 'Renovação de viabilidade locacional', 'active' => true],
        );
        ServiceDeclaration::query()->where('service_type_id', $tipo->id)->delete();
    }

    /**
     * @return array<string, mixed>
     */
    private function formulario(): array
    {
        return ['email' => 'renova@exemplo.com', 'phone' => '7133334444', 'mobile' => '71999990000', 'declaration_ids' => []];
    }

    private function cnpjForaDoAr(): void
    {
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                throw new CnpjLookupException('fora do ar');
            }
        });
    }

    private function mockClient(callable $expectativas): void
    {
        $this->mock(LegadoViabilidadeClient::class, $expectativas);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $numero, ?string $titular): array
    {
        return [
            'numero' => $numero,
            'tipo' => 'provisoria',
            'deferida' => true,
            'ativa' => true,
            'validade' => '2026-12-31',
            'situacao' => 'vigente',
            'dias_para_vencer' => 73,
            'renovada_depois' => false,
            'titular' => ['documento' => $titular],
            'razao_social' => self::RAZAO,
            'endereco' => ['logradouro' => 'RUA DA ENGOMADEIRA', 'numero' => '508', 'bairro' => '', 'cep' => '41200000'],
            'atividades' => [['codigo' => self::CODIGO_CNAE, 'descricao' => 'Padaria']],
        ];
    }

    private function detalhe(int $numero, ?string $titular = self::CNPJ): TvlLegadaDetalhe
    {
        return TvlLegadaDetalhe::fromArray($this->payload($numero, $titular));
    }

    private function resumo(int $numero): TvlLegada
    {
        return TvlLegada::fromArray($this->payload($numero, self::CNPJ));
    }
}
