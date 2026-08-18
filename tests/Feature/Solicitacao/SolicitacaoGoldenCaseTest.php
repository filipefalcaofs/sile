<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\CancelarSolicitacaoService;
use App\Services\Solicitacao\DocumentacaoIncompletaException;
use App\Services\Solicitacao\DocumentRequirementResolver;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use App\Services\Solicitacao\SimulacaoSolicitacaoService;
use App\Services\Solicitacao\SolicitacaoIncompletaException;
use Database\Seeders\DocumentRequirementSeeder;
use Database\Seeders\LouosQuadro7Seeder;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ViabilityServiceTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden cases do fluxo de solicitação de viabilidade (fechamento da Fase 8):
 * casos-âncora entrada→esperado declarados como fixtures JSON e executados pelos
 * SERVIÇOS REAIS (protocolar/cancelar/simular) sobre o SEED OFICIAL (catálogo de
 * tipos/requisitos + Quadro 7 da Lei 9.148/2016 + risco do Decreto 32.636/2020 e
 * da VISA), via #[DataProvider]. É a proteção de regressão do fluxo: se um
 * serviço, o resolver documental ou o motor mudarem e divergirem do esperado, o
 * caso âncora falha com o nome do golden case.
 *
 * Degradação honesta (anti-fachada): os casos provam que a foto da fachada
 * (requisito-base) BLOQUEIA o protocolo quando ausente e que, sem zona oficial, a
 * simulação fica PENDENTE (nunca permitido/não permitido). Espelha o padrão
 * golden das Fases 5/6/7.
 */
class SolicitacaoGoldenCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed OFICIAL do domínio: catálogos-base da fase + motores reais (Quadro
        // 7 e risco). Os golden batem contra o dado real, não fixtures sintéticos.
        $this->seed([
            RolesAndPermissionsSeeder::class,
            ParameterSeeder::class,
            LouosQuadro7Seeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
        ]);
    }

    /**
     * Carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/solicitacao/*.json. Resolve o caminho por __DIR__
     * (o provider roda antes do boot da app).
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/solicitacao/*.json') as $arquivo) {
            /** @var array<string, mixed> $caso */
            $caso = json_decode((string) file_get_contents($arquivo), true, 512, JSON_THROW_ON_ERROR);
            $casos[$caso['nome']] = [$caso];
        }

        return $casos;
    }

    /**
     * @param  array<string, mixed>  $caso
     */
    #[DataProvider('goldenCases')]
    public function test_golden_case(array $caso): void
    {
        $solicitacao = $this->buildDraft($caso);
        $actor = $solicitacao->requester()->firstOrFail();

        $bloqueio = null;
        $simulacao = null;

        try {
            switch ((string) $caso['acao']) {
                case 'protocolar':
                    app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $actor);
                    break;

                case 'protocolar_e_cancelar':
                    app(ProtocolarSolicitacaoService::class)->protocol($solicitacao, $actor);
                    app(CancelarSolicitacaoService::class)->cancel($solicitacao, $actor, 'Cancelada pelo requerente (golden case).');
                    break;

                case 'simular':
                    $simulacao = app(SimulacaoSolicitacaoService::class)->simulate($solicitacao);
                    break;

                default:
                    $this->fail("Ação desconhecida no golden case '{$caso['nome']}': {$caso['acao']}");
            }
        } catch (DocumentacaoIncompletaException) {
            $bloqueio = 'documental';
        } catch (SolicitacaoIncompletaException) {
            $bloqueio = 'dados_minimos';
        }

        $solicitacao->refresh();

        foreach ($caso['esperado'] as $chave => $valorEsperado) {
            $this->assertGolden((string) $caso['nome'], (string) $chave, $valorEsperado, $solicitacao, $bloqueio, $simulacao);
        }
    }

    /**
     * Constrói o rascunho instruído do cenário: empresa/serviço/área/CNAE real e,
     * opcionalmente, a foto da fachada anexada (cobre o requisito-base) e o
     * polígono (ausente força a simulação a degradar para a via CNAE).
     *
     * @param  array<string, mixed>  $caso
     */
    private function buildDraft(array $caso): ViabilityRequest
    {
        $user = User::factory()->cidadao()->withAcceptedLgpdTerm()->create();

        $attributes = [
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
            'is_public_area' => (bool) ($caso['is_public_area'] ?? false),
        ];

        if (($caso['cenario'] ?? null) === 'instruido_sem_poligono') {
            $attributes['property_polygon_geojson'] = null;
        }

        $solicitacao = ViabilityRequest::factory()->draft()->create($attributes);

        $cnae = Cnae::factory()->create(['code' => (string) $caso['cnae']]);
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        if (($caso['anexar_fachada'] ?? false) === true) {
            $fachada = DocumentRequirement::query()
                ->where('code', DocumentRequirementResolver::CODE_FACHADA)
                ->firstOrFail();

            $solicitacao->documents()->create([
                'requirement_id' => $fachada->id,
                'disk' => 'local',
                'path' => "solicitacoes/golden/{$solicitacao->id}-fachada.jpg",
                'original_name' => 'fachada.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 4096,
                'sha256' => hash('sha256', "golden-fachada-{$solicitacao->id}"),
                'uploaded_by_user_id' => $user->id,
            ]);
        }

        return $solicitacao;
    }

    /**
     * Asserta uma chave de `esperado` contra o estado real, com mensagem que
     * identifica o golden case e a chave divergente (regressão de domínio).
     * Espelha o assertGolden das Fases 5/6/7.
     *
     * @param  array<string, mixed>|null  $simulacao
     */
    private function assertGolden(
        string $nome,
        string $chave,
        mixed $esperado,
        ViabilityRequest $solicitacao,
        ?string $bloqueio,
        ?array $simulacao,
    ): void {
        $contexto = "Golden case '{$nome}': divergência em '{$chave}'.";

        switch ($chave) {
            case 'status':
                $this->assertSame($esperado, $solicitacao->status->value, $contexto);
                break;

            case 'protocolo_regex':
                $this->assertMatchesRegularExpression('/'.$esperado.'/', (string) $solicitacao->protocol_number, $contexto);
                break;

            case 'sem_numero':
                $this->assertSame($esperado, $solicitacao->protocol_number === null, $contexto);
                break;

            case 'bloqueio':
                $this->assertSame($esperado, $bloqueio, $contexto);
                break;

            case 'tem_motivo_cancelamento':
                $this->assertSame($esperado, $solicitacao->cancelled_reason !== null, $contexto);
                break;

            case 'simulacao_resultado':
                $this->assertNotNull($simulacao, "{$contexto} A simulação não foi executada.");
                $this->assertSame($esperado, $solicitacao->simulation_resultado, $contexto);
                break;

            case 'risco_municipal_status':
                $this->assertNotNull($simulacao, "{$contexto} A simulação não foi executada.");
                $this->assertSame(
                    $esperado,
                    $solicitacao->simulation_snapshot['por_cnae'][0]['consulta']['risco']['municipal']['status'] ?? null,
                    $contexto,
                );
                break;

            case 'encaminhamento_fluxo':
                $this->assertNotNull($simulacao, "{$contexto} A simulação não foi executada.");
                $this->assertSame(
                    $esperado,
                    $solicitacao->simulation_snapshot['por_cnae'][0]['consulta']['risco']['encaminhamento']['fluxo'] ?? null,
                    $contexto,
                );
                break;

            default:
                throw new \InvalidArgumentException(
                    "Chave de 'esperado' desconhecida no golden case '{$nome}': {$chave}",
                );
        }
    }
}
