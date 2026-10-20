<?php

namespace Tests\Feature\Renovacao;

use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\Parameter;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Cnpj\CnpjData;
use App\Services\Cnpj\CnpjLookup;
use App\Services\Cnpj\CnpjLookupException;
use App\Services\Cnpj\CnpjNotFoundException;
use App\Services\Legado\TvlLegadaDetalhe;
use App\Services\Renovacao\ConferenciaTitularidade;
use App\Services\Renovacao\RenovacaoElegibilidade;
use App\Services\Renovacao\RenovacaoLegadaElegibilidadeService;
use App\Services\Renovacao\ResolvedorEnderecoImovel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RenovacaoLegadaElegibilidadeTest extends TestCase
{
    use FakeCadastroImobiliario;
    use LazilyRefreshDatabase;

    private const CNPJ = '11222333000181';

    private const RAZAO = 'PADARIA ESTRELA LTDA';

    private const CODIGO_CNAE = '4721102';

    private const INSCRICAO = '0514808100';

    protected function setUp(): void
    {
        parent::setUp();

        Cnae::factory()->create(['code' => self::CODIGO_CNAE]);
        $this->cadastroRespondendo($this->dadosCadastrais());
        $this->imovelDaEmpresa();
    }

    public function test_bloqueia_quando_titular_gravado_e_de_outra_empresa(): void
    {
        $resultado = $this->avaliar($this->tvl(titularDocumento: '99888777000166'));

        $this->assertNegado('Esta viabilidade pertence a outra empresa.', $resultado);
    }

    public function test_sem_cnpj_gravado_bloqueia_quando_a_conferencia_falha(): void
    {
        $this->cadastroRespondendo($this->dadosCadastrais(razao: 'OUTRA EMPRESA LTDA'));

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado);
    }

    public function test_cnpj_gravado_incompleto_cai_na_conferencia_cadastral(): void
    {
        $this->cadastroRespondendo($this->dadosCadastrais(razao: 'OUTRA EMPRESA LTDA'));

        $resultado = $this->avaliar($this->tvl(titularDocumento: '123456'));

        $this->assertNegado('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado);
    }

    public function test_sem_cnpj_gravado_e_conferencia_ok_segue_elegivel(): void
    {
        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_cnpj_gravado_igual_dispensa_a_consulta_cadastral(): void
    {
        $this->app->instance(CnpjLookup::class, new class implements CnpjLookup
        {
            public function lookup(string $cnpj): CnpjData
            {
                throw new \LogicException('A consulta cadastral não deveria ser chamada.');
            }
        });

        $resultado = $this->avaliar($this->tvl(titularDocumento: self::CNPJ));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_cnpj_gravado_com_mascara_e_comparado_so_por_digitos(): void
    {
        $resultado = $this->avaliar($this->tvl(titularDocumento: '11.222.333/0001-81'));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_consulta_cadastral_fora_do_ar_bloqueia(): void
    {
        foreach ([new CnpjLookupException('fora do ar'), new CnpjNotFoundException('inexistente')] as $falha) {
            $this->app->instance(CnpjLookup::class, new class($falha) implements CnpjLookup
            {
                public function __construct(private \Throwable $falha) {}

                public function lookup(string $cnpj): CnpjData
                {
                    throw $this->falha;
                }
            });

            $resultado = $this->avaliar($this->tvl(titularDocumento: null));

            $this->assertNegado('Não foi possível conferir o CNPJ agora. Tente novamente em instantes.', $resultado);
        }
    }

    public function test_tvl_sem_inscricao_bloqueia_com_m_sem_inscricao_sem_consultar_o_cadastro(): void
    {
        $chamadas = $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508', '508', '41200-000');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null, inscricao: null));

        $this->assertNegado(ResolvedorEnderecoImovel::M_SEM_INSCRICAO, $resultado);
        $this->assertCount(0, $chamadas);
    }

    public function test_inscricao_nao_encontrada_bloqueia_com_m_nao_encontrada(): void
    {
        $this->cadastroImobiliario(falha: 'nao_encontrada');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $resultado);
    }

    public function test_cadastro_sem_dados_de_endereco_bloqueia_como_nao_encontrada(): void
    {
        $this->cadastroImobiliario(semCadastro: true);

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado(ResolvedorEnderecoImovel::M_NAO_ENCONTRADA, $resultado);
    }

    public function test_endereco_do_imovel_diferente_do_cnpj_bloqueia_com_m_diverge(): void
    {
        $this->cadastroImobiliario('AV SETE DE SETEMBRO, 10', '10', '40060-001');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado(ConferenciaTitularidade::M_DIVERGE, $resultado);
    }

    public function test_endereco_gravado_na_tvl_nao_vale_quando_o_imovel_e_outro(): void
    {
        $this->cadastroImobiliario('AV SETE DE SETEMBRO, 10', '10', '40060-001');

        $resultado = $this->avaliar($this->tvl(
            titularDocumento: null,
            endereco: ['logradouro' => 'RUA DA ENGOMADEIRA', 'numero' => '508', 'bairro' => null, 'cep' => '41200000'],
        ));

        $this->assertNegado(ConferenciaTitularidade::M_DIVERGE, $resultado);
    }

    public function test_imovel_confere_por_logradouro_e_numero_quando_o_cep_difere(): void
    {
        $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508 — SALA 2', '508', '40000-000');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_imovel_sem_cep_utilizavel_e_sem_numero_nao_casa_com_ninguem(): void
    {
        $this->cadastroImobiliario('RUA DA ENGOMADEIRA', 'S/N', '00000-000');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado(ConferenciaTitularidade::M_DIVERGE, $resultado);
    }

    public function test_cadastro_fora_do_ar_e_consulta_indisponivel(): void
    {
        $this->cadastroImobiliario(falha: 'indisponivel');
        $tvl = $this->tvl(titularDocumento: null);
        $empresa = $this->empresa();

        $titularidade = app(RenovacaoLegadaElegibilidadeService::class)->titularidade($tvl, $empresa);
        $resultado = $this->avaliar($tvl);

        $this->assertTrue($titularidade->consultaIndisponivel);
        $this->assertFalse($titularidade->titular);
        $this->assertSame(ResolvedorEnderecoImovel::M_INDISPONIVEL, $titularidade->motivo);
        $this->assertNegado(ResolvedorEnderecoImovel::M_INDISPONIVEL, $resultado);
    }

    public function test_razao_diferente_nao_consulta_o_cadastro_imobiliario(): void
    {
        $chamadas = $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508', '508', '41200-000');
        $this->cadastroRespondendo($this->dadosCadastrais(razao: 'OUTRA EMPRESA LTDA'));

        $resultado = $this->avaliar($this->tvl(titularDocumento: null));

        $this->assertNegado('A razão social deste CNPJ não confere com a da viabilidade anterior.', $resultado);
        $this->assertCount(0, $chamadas);
    }

    public function test_inscricao_com_mascara_chega_ao_cadastro_so_com_digitos(): void
    {
        $chamadas = $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508', '508', '41200-000');

        $resultado = $this->avaliar($this->tvl(titularDocumento: null, inscricao: '051.480-81'));

        $this->assertTrue($resultado->elegivel);
        $this->assertSame(['05148081'], $chamadas->getArrayCopy());
    }

    public function test_cnpj_gravado_igual_nao_consulta_o_cadastro_imobiliario(): void
    {
        $chamadas = $this->cadastroImobiliario(falha: 'indisponivel');

        $resultado = $this->avaliar($this->tvl(titularDocumento: self::CNPJ, inscricao: null));

        $this->assertTrue($resultado->elegivel);
        $this->assertCount(0, $chamadas);
    }

    public function test_cnpj_gravado_de_outra_empresa_nao_consulta_o_cadastro_imobiliario(): void
    {
        $chamadas = $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508', '508', '41200-000');

        $resultado = $this->avaliar($this->tvl(titularDocumento: '99888777000166'));

        $this->assertNegado('Esta viabilidade pertence a outra empresa.', $resultado);
        $this->assertCount(0, $chamadas);
    }

    public function test_bloqueia_quando_nao_e_provisoria(): void
    {
        $resultado = $this->avaliar($this->tvl(tipo: 'definitiva'));

        $this->assertNegado('Somente viabilidade provisória deferida pode ser renovada.', $resultado);
    }

    public function test_bloqueia_quando_nao_foi_deferida(): void
    {
        $resultado = $this->avaliar($this->tvl(deferida: false));

        $this->assertNegado('Somente viabilidade provisória deferida pode ser renovada.', $resultado);
    }

    public function test_bloqueia_quando_nao_ha_atividades(): void
    {
        $resultado = $this->avaliar($this->tvl(atividades: []));

        $this->assertNegado('A viabilidade anterior não tem atividades registradas.', $resultado);
    }

    public function test_bloqueia_atividade_fora_do_catalogo(): void
    {
        $resultado = $this->avaliar($this->tvl(atividades: [
            ['codigo' => '4721-1/02', 'descricao' => 'Padaria'],
            ['codigo' => '9999-9/99', 'descricao' => 'Inexistente'],
        ]));

        $this->assertNegado('A atividade 9999-9/99 não consta mais do catálogo. Procure a SEDUR.', $resultado);
    }

    public function test_atividade_inativa_no_catalogo_bloqueia(): void
    {
        Cnae::factory()->inactive()->create(['code' => '5611201']);

        $resultado = $this->avaliar($this->tvl(atividades: [['codigo' => '5611201', 'descricao' => 'Restaurante']]));

        $this->assertNegado('A atividade 5611201 não consta mais do catálogo. Procure a SEDUR.', $resultado);
    }

    public function test_codigo_legado_com_mascara_e_normalizado_para_o_catalogo(): void
    {
        $resultado = $this->avaliar($this->tvl(atividades: [['codigo' => '4721-1/02', 'descricao' => 'Padaria']]));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_bloqueia_quando_o_legado_ja_registra_renovacao_posterior(): void
    {
        $resultado = $this->avaliar($this->tvl(renovadaDepois: true));

        $this->assertNegado('Esta viabilidade já foi renovada.', $resultado);
    }

    public function test_bloqueia_vencida_quando_o_parametro_nao_permite(): void
    {
        $this->definirPermiteVencida('0');

        $resultado = $this->avaliar($this->tvl(situacao: 'vencida'));

        $this->assertNegado('Viabilidade vencida não pode ser renovada.', $resultado);
    }

    public function test_vencida_e_permitida_por_padrao(): void
    {
        $resultado = $this->avaliar($this->tvl(situacao: 'vencida'));

        $this->assertTrue($resultado->elegivel);
    }

    public function test_bloqueia_quando_renovada_no_viabiliza_ha_menos_de_tres_meses(): void
    {
        $this->renovacaoNoViabiliza(ViabilityRequestStatus::Deferida, now()->subMonth());

        $resultado = $this->avaliar($this->tvl());

        $this->assertNegado('Esta viabilidade foi renovada há menos de 3 meses.', $resultado);
    }

    public function test_bloqueia_quando_ja_renovada_no_viabiliza_fora_do_intervalo(): void
    {
        $this->renovacaoNoViabiliza(ViabilityRequestStatus::Deferida, now()->subMonths(6));

        $resultado = $this->avaliar($this->tvl());

        $this->assertNegado('Esta viabilidade já foi renovada.', $resultado);
    }

    public function test_renovacao_deferida_de_outra_tvl_legada_nao_interfere(): void
    {
        $this->renovacaoNoViabiliza(ViabilityRequestStatus::Deferida, now()->subMonth(), numero: 999);

        $resultado = $this->avaliar($this->tvl());

        $this->assertTrue($resultado->elegivel);
    }

    /**
     * @return array<string, array{ViabilityRequestStatus}>
     */
    public static function statusEmAberto(): array
    {
        return [
            'protocolada' => [ViabilityRequestStatus::Protocolada],
            'em analise' => [ViabilityRequestStatus::EmAnalise],
            'em pendencia' => [ViabilityRequestStatus::EmPendencia],
        ];
    }

    #[DataProvider('statusEmAberto')]
    public function test_bloqueia_quando_ha_renovacao_em_aberto_no_viabiliza(ViabilityRequestStatus $status): void
    {
        $this->renovacaoNoViabiliza($status);

        $resultado = $this->avaliar($this->tvl());

        $this->assertNegado(
            'Para cadastrar novo processo de renovação, aguarde a conclusão da análise ou solicite desistência do processo em análise.',
            $resultado,
        );
    }

    public function test_elegivel_quando_tudo_confere(): void
    {
        $resultado = $this->avaliar($this->tvl());

        $this->assertTrue($resultado->elegivel);
        $this->assertNull($resultado->motivo);
    }

    public function test_primeira_regra_que_falha_vence(): void
    {
        $this->renovacaoNoViabiliza(ViabilityRequestStatus::EmAnalise);

        $resultado = $this->avaliar($this->tvl(
            titularDocumento: '99888777000166',
            tipo: 'definitiva',
            atividades: [],
            renovadaDepois: true,
        ));

        $this->assertNegado('Esta viabilidade pertence a outra empresa.', $resultado);
    }

    public function test_ordem_entre_regras_posteriores_e_respeitada(): void
    {
        $this->renovacaoNoViabiliza(ViabilityRequestStatus::EmAnalise);

        $resultado = $this->avaliar($this->tvl(atividades: [], renovadaDepois: true));

        $this->assertNegado('A viabilidade anterior não tem atividades registradas.', $resultado);
    }

    private function assertNegado(string $motivo, object $resultado): void
    {
        $this->assertFalse($resultado->elegivel);
        $this->assertSame($motivo, $resultado->motivo);
    }

    private function avaliar(TvlLegadaDetalhe $tvl): RenovacaoElegibilidade
    {
        return app(RenovacaoLegadaElegibilidadeService::class)->avaliar($tvl, $this->empresa());
    }

    private function empresa(): Company
    {
        return Company::query()->where('cnpj', self::CNPJ)->first()
            ?? Company::factory()->create(['cnpj' => self::CNPJ]);
    }

    private function imovelDaEmpresa(): void
    {
        $this->cadastroImobiliario('RUA DA ENGOMADEIRA, 508 — SALA 2', '508', '41200-000');
    }

    private function definirPermiteVencida(string $valor): void
    {
        Parameter::query()->where('key', 'renovacao.legado.permite_vencida')->update(['value' => $valor]);
        Cache::forget('viabiliza.parameters.renovacao.legado.permite_vencida');
    }

    private function renovacaoNoViabiliza(
        ViabilityRequestStatus $status,
        ?\DateTimeInterface $decididaEm = null,
        int $numero = 4321,
    ): ViabilityRequest {
        $renovacao = ViabilityRequest::factory()->create(['renews_legacy_tvl_number' => $numero]);
        $renovacao->forceFill(['status' => $status])->save();

        if ($decididaEm !== null) {
            ViabilityDecision::factory()->create([
                'viability_request_id' => $renovacao->id,
                'tvl_product_number' => null,
                'decided_at' => $decididaEm,
            ]);
        }

        return $renovacao;
    }

    private function cadastroRespondendo(CnpjData $dados): void
    {
        $this->app->instance(CnpjLookup::class, new class($dados) implements CnpjLookup
        {
            public function __construct(private CnpjData $dados) {}

            public function lookup(string $cnpj): CnpjData
            {
                return $this->dados;
            }
        });
    }

    private function dadosCadastrais(string $razao = self::RAZAO): CnpjData
    {
        return new CnpjData(
            cnpj: self::CNPJ,
            legalName: $razao,
            tradeName: null,
            legalNatureCode: null,
            legalNature: null,
            sizeCode: null,
            size: null,
            primaryCnaeCode: null,
            secondaryCnaeCodes: [],
            street: 'RUA DA ENGOMADEIRA',
            number: '508',
            complement: null,
            neighborhood: null,
            city: null,
            state: null,
            zipCode: '41200000',
            phone: null,
            email: null,
            registrationStatus: null,
        );
    }

    /**
     * @param  list<array{codigo: string, descricao: string}>|null  $atividades
     * @param  array{logradouro: ?string, numero: ?string, bairro: ?string, cep: ?string}|null  $endereco
     */
    private function tvl(
        ?string $titularDocumento = self::CNPJ,
        string $tipo = 'provisoria',
        bool $deferida = true,
        ?array $atividades = null,
        bool $renovadaDepois = false,
        ?string $situacao = 'vigente',
        ?string $inscricao = self::INSCRICAO,
        ?array $endereco = null,
    ): TvlLegadaDetalhe {
        return new TvlLegadaDetalhe(
            numero: 4321,
            tipo: $tipo,
            deferida: $deferida,
            ativa: true,
            emissao: null,
            validade: null,
            situacao: $situacao,
            diasParaVencer: null,
            renovadaDepois: $renovadaDepois,
            ultimaRenovacaoEm: null,
            titularDocumento: $titularDocumento,
            titularTipo: null,
            titularOrigem: null,
            razaoSocial: self::RAZAO,
            inscricaoImobiliaria: $inscricao,
            endereco: $endereco ?? ['logradouro' => 'AV QUALQUER', 'numero' => '1', 'bairro' => null, 'cep' => '40000000'],
            processoNumero: null,
            complemento: null,
            zona: null,
            via: null,
            flags: ['sede' => false, 'mei' => false, 'escritorio_virtual' => false, 'culto_religioso' => false, 'edificio_comercial' => false],
            condicionanteAdicional: null,
            atividades: $atividades ?? [['codigo' => self::CODIGO_CNAE, 'descricao' => 'Padaria']],
            renovacoes: [],
        );
    }
}
