<?php

namespace Tests\Feature\Expresso;

use App\Enums\ViabilityRequestStatus;
use App\Events\ResultadoEmitido;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Inscrição imobiliária que não está ativa na SEFAZ Municipal (situação
 * cadastral diferente de "0") indefere a solicitação, com o parecer oficial.
 */
class IndeferimentoInscricaoInativaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PARECER = "Solicitação indeferida em razão de pendências identificadas na situação cadastral da inscrição imobiliária junto à SEFAZ Municipal.\n\nRecomenda-se a regularização das pendências junto à SEFAZ Municipal para possibilitar o cadastramento de um novo processo.";

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DecisionTextSeeder::class);
    }

    private function cadastroComSituacao(?string $situacao, bool $indisponivel = false): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($situacao, $indisponivel) implements PropertyRegistryLookup
        {
            public function __construct(private ?string $situacao, private bool $indisponivel) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                if ($this->indisponivel) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                $campos = PropertyCadastroCampos::vazios();
                $dados = $campos->toArray();
                $dados['situacao_cadastral'] = $this->situacao;
                $dados['inscricao'] = $inscricao;

                return new PropertyRegistryResult(
                    latitude: -12.97,
                    longitude: -38.51,
                    inscricao: $inscricao,
                    source: 'teste',
                    raw: ['Status' => $this->situacao],
                    cadastro: new PropertyCadastroCampos(...$dados),
                );
            }
        });
    }

    private function protocolada(?string $inscricao = '344672'): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => $inscricao,
            'used_area_m2' => 120.0,
        ]);
    }

    public function test_situacao_diferente_de_zero_indefere_com_o_parecer_oficial(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao('1');
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertSame(ViabilityRequestStatus::Indeferida, $resultado->status);
        $this->assertSame(ViabilityRequestStatus::Indeferida, $solicitacao->fresh()->status);
        $this->assertSame(self::PARECER, $resultado->decision->reason);
        $this->assertSame('inscricao_inativa', $resultado->decision->consolidated_result);
        $this->assertNull($resultado->decision->tvl_product_number);
        Event::assertDispatched(ResultadoEmitido::class);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'expresso', 'event' => 'decisao', 'result' => 'indeferida']);
    }

    public function test_situacao_em_texto_diferente_de_ativa_tambem_indefere(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao('BAIXADA');
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }

    public function test_situacao_quinze_ativa_por_decreto_nao_indefere_por_este_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao('15');
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertNotSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }

    public function test_situacoes_de_um_a_dezessete_exceto_quinze_indeferem(): void
    {
        foreach (array_diff(range(1, 17), [15]) as $codigo) {
            $this->assertTrue(
                (new LocalizacaoInscricao(LocalizacaoInscricao::LOCALIZADA, situacaoCadastral: (string) $codigo))->inscricaoInativa(),
                "código {$codigo}",
            );
        }
    }

    public function test_situacao_zero_com_espacos_e_ativa_e_nao_indefere_por_este_motivo(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao(' 0 ');
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertNotSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }

    public function test_cadastro_indisponivel_nao_indefere(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao(null, indisponivel: true);
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertNotSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }

    public function test_situacao_nao_informada_nao_indefere(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao(null);
        $solicitacao = $this->protocolada();

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertNotSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }

    public function test_sem_inscricao_nao_consulta_nem_indefere(): void
    {
        Event::fake([ResultadoEmitido::class]);
        $this->cadastroComSituacao('1');
        $solicitacao = $this->protocolada(null);

        $resultado = app(FluxoExpressoService::class)->decide($solicitacao);

        $this->assertNotSame('inscricao_inativa', $resultado->decision?->consolidated_result);
    }
}
