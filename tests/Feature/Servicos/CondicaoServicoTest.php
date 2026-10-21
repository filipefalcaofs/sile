<?php

namespace Tests\Feature\Servicos;

use App\Enums\CondicaoServico;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\ReginRecebimento;
use App\Models\ServicoNomenclatura;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Regin\ReginEventos;
use App\Services\Servicos\CondicaoServicoDetector;
use App\Services\Servicos\ServicoNomenclaturaResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Linhas condicionais do De x Para da SEDUR: a alteração de atividades vira
 * Inclusão ou Exclusão e a alteração de endereço no município vira Revisão de
 * Endereço ou Alteração de Espaço, comparando com o último processo da empresa.
 */
class CondicaoServicoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ATIVIDADES = 'Alteração de atividades econômicas (principal e secundárias)';

    private const ENDERECO = 'Alteração de endereço dentro do mesmo município';

    /**
     * @param  list<string>  $cnaes
     * @param  array<string, mixed>  $atributos
     */
    private function processo(Company $empresa, array $cnaes = [], array $atributos = []): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->create([
            'company_id' => $empresa->id,
            'address_street' => 'Rua A',
            'address_number' => '10',
            'address_neighborhood' => 'Centro',
            'address_complement' => 'Sala 1',
            'address_zip' => '40000000',
            ...$atributos,
        ]);

        foreach ($cnaes as $indice => $codigo) {
            $cnae = Cnae::query()->firstOrCreate(['code' => $codigo], Cnae::factory()->make(['code' => $codigo])->getAttributes());
            $processo->cnaes()->attach($cnae->id, ['is_primary' => $indice === 0]);
        }

        return $processo;
    }

    private function detectar(ViabilityRequest $processo, string $evento): ?CondicaoServico
    {
        return app(CondicaoServicoDetector::class)->detectar($processo, $evento);
    }

    public function test_carga_fica_igual_a_tabela_da_sedur(): void
    {
        $linhas = ServicoNomenclatura::query()->orderBy('id')->get()
            ->map(fn (ServicoNomenclatura $l): array => [$l->de, $l->para, $l->condicao])
            ->all();

        $this->assertSame([
            ['Inscrição de primeiro estabelecimento', 'Viabilidade de Localização', ''],
            ['Inscrição dos demais estabelecimentos', 'Viabilidade de Localização', ''],
            [self::ATIVIDADES, 'Revisão de Atividade - Inclusão', 'cnae_incluido'],
            [self::ENDERECO, 'Revisão de Endereço', 'endereco_localizacao'],
            ['Alteração de endereço entre estados', 'Revisão de Endereço', ''],
            ['Alteração de endereço entre municípios dentro do mesmo estado', 'Revisão de Endereço', ''],
            [self::ATIVIDADES, 'Revisão de Atividade - Exclusão', 'cnae_excluido'],
            [self::ENDERECO, 'Revisão de Endereço - Alteração de Espaço', 'endereco_espaco'],
        ], $linhas);
    }

    public function test_so_incluindo_cnae_e_inclusao(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, ['4711301']);
        $atual = $this->processo($empresa, ['4711301', '5611201']);

        $this->assertSame(CondicaoServico::CnaeIncluido, $this->detectar($atual, self::ATIVIDADES));
    }

    public function test_incluindo_e_excluindo_cnae_e_exclusao(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, ['4711301', '4712100']);
        $atual = $this->processo($empresa, ['4711301', '5611201']);

        $this->assertSame(CondicaoServico::CnaeExcluido, $this->detectar($atual, self::ATIVIDADES));
    }

    public function test_sem_processo_anterior_nao_ha_condicao(): void
    {
        $atual = $this->processo(Company::factory()->create(), ['4711301']);

        $this->assertNull($this->detectar($atual, self::ATIVIDADES));
        $this->assertNull($this->detectar($atual, self::ENDERECO));
    }

    public function test_compara_com_o_processo_mais_recente_da_mesma_empresa(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, ['4712100']);
        $this->processo($empresa, ['4711301']);
        $this->processo(Company::factory()->create(), ['9999999']);
        $atual = $this->processo($empresa, ['4711301', '5611201']);

        $this->assertSame(CondicaoServico::CnaeIncluido, $this->detectar($atual, self::ATIVIDADES));
    }

    public function test_mudar_numero_bairro_ou_codlog_e_revisao_de_endereco(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, [], ['cod_log' => '123']);

        $this->assertSame(CondicaoServico::EnderecoLocalizacao, $this->detectar($this->processo($empresa, [], ['address_number' => '20']), self::ENDERECO));
        $this->assertSame(CondicaoServico::EnderecoLocalizacao, $this->detectar($this->processo($empresa, [], ['address_neighborhood' => 'Barra', 'cod_log' => '123']), self::ENDERECO));
    }

    public function test_mudar_so_complemento_ou_cep_e_alteracao_de_espaco(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa);

        $this->assertSame(CondicaoServico::EnderecoEspaco, $this->detectar($this->processo($empresa, [], ['address_complement' => 'Sala 2']), self::ENDERECO));
        $this->assertSame(CondicaoServico::EnderecoEspaco, $this->detectar($this->processo($empresa, [], ['address_complement' => 'Sala 2', 'address_zip' => '40000001']), self::ENDERECO));
    }

    public function test_compara_endereco_sem_caixa_e_espacos(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa);

        $this->assertNull($this->detectar($this->processo($empresa, [], ['address_neighborhood' => ' CENTRO ', 'address_complement' => 'sala  1']), self::ENDERECO));
    }

    public function test_evento_sem_linha_condicional_nao_tem_condicao(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, ['4712100']);

        $this->assertNull($this->detectar($this->processo($empresa, ['4711301']), 'Inscrição dos demais estabelecimentos'));
    }

    public function test_gravar_registra_a_condicao_no_evento(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, ['4711301', '4712100']);
        $atual = $this->processo($empresa, ['4711301']);
        $recebimento = ReginRecebimento::factory()->create([
            'corpo' => ['rowset' => ['GROUPPSC_PROT_EVENTO_RFB' => ['PSC_PROT_EVENTO_RFB' => [
                ['PEV_COD_EVENTO' => '244', 'PEV_NOME_EVENTO' => self::ATIVIDADES],
            ]]]],
        ]);

        ReginEventos::gravar($atual, $recebimento);

        $this->assertSame('cnae_excluido', $atual->eventos()->first()->condicao);
    }

    public function test_exibe_o_para_da_condicao_do_evento(): void
    {
        $exclusao = ViabilityRequest::factory()->create();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $exclusao->id, 'codigo' => '244', 'nome' => self::ATIVIDADES, 'condicao' => 'cnae_excluido']);
        $semCondicao = ViabilityRequest::factory()->create();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $semCondicao->id, 'codigo' => '244', 'nome' => self::ATIVIDADES, 'condicao' => null]);
        $espaco = ViabilityRequest::factory()->create();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $espaco->id, 'codigo' => '300', 'nome' => self::ENDERECO, 'condicao' => 'endereco_espaco']);

        $resolver = app(ServicoNomenclaturaResolver::class);

        $this->assertSame(['Revisão de Atividade - Exclusão'], $resolver->servicos($exclusao));
        $this->assertSame(['Revisão de Atividade - Inclusão'], $resolver->servicos($semCondicao));
        $this->assertSame(['Revisão de Endereço - Alteração de Espaço'], $resolver->servicos($espaco));
    }

    public function test_filtro_separa_inclusao_de_exclusao(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $exclusao = ViabilityRequest::factory()->create();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $exclusao->id, 'codigo' => '244', 'nome' => self::ATIVIDADES, 'condicao' => 'cnae_excluido']);
        $inclusao = ViabilityRequest::factory()->create();
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $inclusao->id, 'codigo' => '244', 'nome' => self::ATIVIDADES, 'condicao' => 'cnae_incluido']);

        $opcoes = collect(app(ServicoNomenclaturaResolver::class)->opcoesDeEvento())->pluck('label')->all();
        $this->assertContains('Revisão de Atividade - Exclusão', $opcoes);
        $this->assertContains('Revisão de Atividade - Inclusão', $opcoes);

        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $this->actingAs($admin, 'gestao')
            ->get('/gestao/processos?servico='.urlencode('nrevisao de atividade - exclusao'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $exclusao->id));
    }

    public function test_comando_preenche_a_condicao_dos_eventos_ja_gravados(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa);
        $atual = $this->processo($empresa, [], ['address_zip' => '40000009']);
        $evento = ViabilityRequestEvento::factory()->create(['viability_request_id' => $atual->id, 'codigo' => '300', 'nome' => self::ENDERECO, 'condicao' => null]);

        $this->artisan('servicos:detectar-condicoes')->assertSuccessful();

        $this->assertSame('endereco_espaco', $evento->fresh()->condicao);
    }

    public function test_tela_cria_linha_com_condicao_e_mostra_o_rotulo(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/servicos-nomenclatura', ['de' => 'Alteração de nome empresarial', 'para' => 'Nome', 'exibir' => '1', 'condicao' => ''])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/servicos-nomenclatura', ['de' => self::ATIVIDADES, 'para' => 'Outro', 'exibir' => '1', 'condicao' => 'cnae_excluido'])
            ->assertSessionHasErrors(['de' => 'Já existe uma linha para este serviço.']);

        $this->actingAs($admin, 'gestao')
            ->get('/gestao/servicos-nomenclatura?search=atividades')
            ->assertInertia(fn (Assert $page) => $page
                ->has('nomenclaturas.data', 2)
                ->where('nomenclaturas.data.1.condicao_label', CondicaoServico::CnaeExcluido->label())
                ->has('condicaoOptions', 4));
    }
}
