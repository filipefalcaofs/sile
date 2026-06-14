<?php

namespace Tests\Feature\Louos;

use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use Database\Seeders\LouosQuadro10Seeder;
use Database\Seeders\LouosQuadro11Seeder;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Golden cases do motor de enquadramento da LOUOS (critério 7 do ROADMAP — Fase
 * 5): casos entrada→esperado declarados como fixtures JSON e executados pelo
 * motor REAL (LouosEnquadramentoService) sobre o SEED OFICIAL (Quadros 7/10/11
 * derivados da Lei nº 9.148/2016), via #[DataProvider]. É a proteção de
 * regressão de domínio: se o dado oficial ou o motor mudar e divergir do
 * esperado, o caso âncora falha com o nome do golden case — sinal para
 * reconferência pela SEDUR.
 *
 * Degradação honesta (anti-fachada): os casos marcados `requer_zona` (e os sem
 * zona no input) esperam `resultado: pendente` enquanto a base oficial de zona
 * não chega — a zona urbanística da LOUOS segue bloqueada pendente SEDUR
 * (Fase 4, decisão do 05-CONTEXT). O motor NUNCA declara permitido/nao_permitido
 * sem o dado real; só o caso com zona explícita (entrada do operador) chega a um
 * veredito decidível (ex.: proibido → nao_permitido).
 *
 * O motor é tabular: roda em SQLite (:memory:), sem PostGIS.
 */
class LouosGoldenCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed OFICIAL: os golden cases batem contra o dado real (Quadros
        // versionados da Lei 9.148/2016), não fixtures sintéticos (anti-fachada).
        $this->seed([
            LouosQuadro7Seeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
        ]);
    }

    /**
     * Data provider: carrega cada fixture entrada→esperado de
     * tests/Fixtures/golden/louos/*.json. Não usa o container (base_path) porque
     * o provider roda antes do boot da app — resolve o caminho por __DIR__.
     *
     * @return array<string, array{0: array<string, mixed>}>
     */
    public static function goldenCases(): array
    {
        $casos = [];

        foreach (glob(dirname(__DIR__, 2).'/Fixtures/golden/louos/*.json') as $arquivo) {
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

        $result = app(LouosEnquadramentoService::class)->enquadrar($input);

        foreach ($caso['esperado'] as $chave => $valorEsperado) {
            $this->assertGolden($nome, (string) $chave, $valorEsperado, $result);
        }
    }

    /**
     * Monta o EnquadramentoInput a partir do bloco `input` do fixture: área e
     * CNAE principal sempre; território só quando há `zona` (entrada explícita do
     * operador). Sem zona / `requer_zona` => território nulo (a zona degrada).
     *
     * @param  array<string, mixed>  $input
     */
    private function montaInput(array $input): EnquadramentoInput
    {
        /** @var array<string, mixed> $vagas */
        $vagas = $input['vagas_declaradas'] ?? [];

        return new EnquadramentoInput(
            area: (float) $input['area'],
            cnaePrincipal: (string) $input['cnae'],
            territory: $this->montaTerritorio($input),
            vagasDeclaradas: $vagas,
        );
    }

    /**
     * Território do golden case. Sem zona (ou `requer_zona`): território nulo → o
     * Quadro 10 degrada para `indisponivel` e o consolidado fica `pendente`
     * (degradação honesta — zona pendente SEDUR). Com zona: feição identificada
     * como ENTRADA EXPLÍCITA do operador, com as restrições do fixture (ex.:
     * ZEIS) quando presentes.
     *
     * @param  array<string, mixed>  $input
     */
    private function montaTerritorio(array $input): ?TerritoryResult
    {
        $zona = $input['zona'] ?? null;

        if (! is_string($zona) || $zona === '') {
            return null;
        }

        /** @var list<string> $restricoes */
        $restricoes = $input['restricoes'] ?? [];

        return new TerritoryResult(
            bairro: $this->dimVazia(),
            via: $this->dimVazia() + ['distancia_m' => null],
            zona: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'nome' => $zona,
                'propriedades' => ['NOME' => $zona],
                'motivo' => null,
                'versao_camada' => 'zona-hipotetica-golden',
            ],
            lote: $this->dimVazia(),
            restricoes: $this->montaRestricoes($restricoes),
        );
    }

    /**
     * Restrições territoriais (camada ambiental da Fase 4 — ex.: ZEIS) a partir
     * da lista de nomes do fixture. Vazio => dimensão `nao_encontrado`.
     *
     * @param  list<string>  $itens
     * @return array<string, mixed>
     */
    private function montaRestricoes(array $itens): array
    {
        if ($itens === []) {
            return ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null];
        }

        return [
            'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
            'itens' => array_map(
                static fn (string $nome): array => ['nome' => $nome, 'propriedades' => []],
                $itens,
            ),
            'motivo' => null,
            'versao_camada' => 'restricoes-golden',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimVazia(): array
    {
        return [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];
    }

    /**
     * Asserta uma chave de `esperado` contra o EnquadramentoResult real, com
     * mensagem que identifica o golden case e a chave divergente (regressão de
     * domínio). Espelha RiscoGoldenCaseTest::assertGolden.
     */
    private function assertGolden(string $nome, string $chave, mixed $esperado, EnquadramentoResult $result): void
    {
        $atual = match ($chave) {
            'quadro7_status' => $result->quadro7['status'] ?? null,
            'quadro7_grupo' => $result->quadro7['grupo'] ?? null,
            'quadro7_subgrupo' => $result->quadro7['subgrupo'] ?? null,
            'quadro10_status' => $result->quadro10['status'] ?? null,
            'quadro10_permissao' => $result->quadro10['permissao'] ?? null,
            'quadro11_status' => $result->quadro11['status'] ?? null,
            'quadro11a_status' => $result->quadro11a['status'] ?? null,
            'resultado' => $result->resultado(),
            'motivo' => $result->consolidado['motivo'] ?? null,
            default => throw new \InvalidArgumentException(
                "Chave de 'esperado' desconhecida no golden case '{$nome}': {$chave}",
            ),
        };

        $this->assertSame(
            $esperado,
            $atual,
            "Golden case '{$nome}': divergência em '{$chave}'. Esperado ".json_encode($esperado, JSON_UNESCAPED_UNICODE)
                .', obtido '.json_encode($atual, JSON_UNESCAPED_UNICODE).'.',
        );
    }
}
