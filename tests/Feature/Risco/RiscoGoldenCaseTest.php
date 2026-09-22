<?php

namespace Tests\Feature\Risco;

use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use Carbon\Carbon;
use Database\Seeders\RiscoMunicipalSeeder;
use Database\Seeders\RiscoSanitarioSeeder;
use Database\Seeders\RiskTriggerSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden cases da classificação de risco (critério 6 do ROADMAP — Fase 6):
 * casos entrada→esperado declarados como fixtures JSON e executados pelo motor
 * REAL (RiscoClassificationService) sobre o SEED OFICIAL (Decreto 32.636/2020 +
 * planilha VISA + gatilhos), via #[DataProvider]. É a proteção de regressão de
 * domínio: se o dado oficial ou o motor mudar e divergir do esperado, o caso
 * âncora falha com o nome do golden case — sinal para reconferência pela SEDUR.
 *
 * O motor é tabular: roda em SQLite (:memory:), sem PostGIS.
 */
class RiscoGoldenCaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed OFICIAL: os golden cases batem contra o dado real, não fixtures
        // sintéticos do dado (anti-fachada).
        $this->seed([
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
        ]);
    }

    /**
     * Data provider: carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/risco/*.json. Não usa o container (base_path) porque
     * o provider roda antes do boot da app — resolve o caminho por __DIR__.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/risco/*.json') as $arquivo) {
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
        $nome = (string) $caso['nome'];
        $input = $this->montaInput($caso['input']);

        $result = app(RiscoClassificationService::class)->classify($input);

        foreach ($caso['esperado'] as $chave => $valorEsperado) {
            $this->assertGolden($nome, (string) $chave, $valorEsperado, $result);
        }
    }

    /**
     * Monta o RiscoInput a partir do bloco `input` do fixture. Sem respostas de
     * condicionantes: o e-mail SEDUR de 21/09/2026 (item 4) retirou a
     * reclassificação VISA do motor — o nível final é sempre o da tabela.
     *
     * @param  array<string, mixed>  $input
     */
    private function montaInput(array $input): RiscoInput
    {
        return new RiscoInput(
            cnaeCode: (string) $input['cnae_code'],
            gatilhosContexto: $input['gatilhos_contexto'] ?? [],
            data: isset($input['data']) ? Carbon::parse($input['data']) : null,
        );
    }

    /**
     * Asserta uma chave de `esperado` contra o RiscoResult real, com mensagem
     * que identifica o golden case e a chave divergente (regressão de domínio).
     */
    private function assertGolden(string $nome, string $chave, mixed $esperado, RiscoResult $result): void
    {
        $atual = match ($chave) {
            'municipal_status' => $result->municipal['status'] ?? null,
            'municipal_nivel' => $result->municipal['nivel'] ?? null,
            'sanitario_status' => $result->sanitario['status'] ?? null,
            'sanitario_nivel_original' => $result->sanitario['nivel_original'] ?? null,
            'sanitario_nivel_final' => $result->sanitario['nivel_final'] ?? null,
            'sanitario_reclassificado' => $result->sanitario['reclassificado'] ?? null,
            'fluxo' => $result->encaminhamento['fluxo'] ?? null,
            'dimensao_decisiva' => $result->encaminhamento['dimensao_decisiva'] ?? null,
            'gatilhos_acionados' => array_column($result->encaminhamento['gatilhos_acionados'] ?? [], 'codigo'),
            default => throw new \InvalidArgumentException(
                "Chave de 'esperado' desconhecida no golden case '{$nome}': {$chave}",
            ),
        };

        if ($chave === 'gatilhos_acionados') {
            sort($esperado);
            sort($atual);
        }

        $this->assertSame(
            $esperado,
            $atual,
            "Golden case '{$nome}': divergência em '{$chave}'. Esperado ".json_encode($esperado, JSON_UNESCAPED_UNICODE)
                .', obtido '.json_encode($atual, JSON_UNESCAPED_UNICODE).'.',
        );
    }
}
