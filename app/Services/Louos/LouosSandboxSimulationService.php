<?php

namespace App\Services\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Geo\TerritoryResult;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/**
 * Sandbox de simulação de impacto de parametrização da LOUOS (HU-143). Reexecuta
 * o MOTOR REAL (LouosEnquadramentoService) com uma versão RASCUNHO de um Quadro
 * (modo "versão específica" via versoesOverride — 05-03) sobre cenários derivados
 * dos CNAEs da versão vigente do Quadro 7, e compara o veredito consolidado com o
 * da versão vigente, contando as divergências — antes de qualquer publicação.
 *
 * Escopo honesto da amostra (sem fachada): os cenários são DERIVADOS dos dados
 * reais já seedados (CNAEs do Quadro 7 vigente + uma zona fixa do Quadro 10
 * vigente, para o consolidado ir além de `pendente`). Não há arquivo de cenário
 * próprio: quando o EP08 trouxer processos reais, a mesma simulação passa a usá-los
 * — muda a fonte da amostra, não a lógica.
 *
 * RN-001 (zero efeito colateral): a simulação NÃO altera a versão vigente, NÃO
 * publica, NÃO dispara integração/notificação e NÃO audita decisão. O motor
 * roda dentro de uma transação SEMPRE revertida (`reexecutarSemPersistir`), de
 * modo que nenhuma escrita do motor durante a simulação persiste; só o registro
 * informativo da própria simulação (event `simulacao`, sem versão de publicação)
 * é gravado, depois, fora dessa transação.
 */
class LouosSandboxSimulationService
{
    public function __construct(
        private LouosEnquadramentoService $engine,
        private AuditService $audit,
    ) {}

    /**
     * Simula o impacto da versão `$versaoRascunho` do domínio `$domain` contra a
     * amostra de cenários, sem efeito colateral. Devolve o relatório
     * `{dominio, versao_rascunho, amostra_usada, total, mudariam, distribuicao, divergencias[]}`.
     *
     * @return array<string, mixed>
     */
    public function simulate(RuleDomain $domain, string $versaoRascunho, ?int $amostra = null): array
    {
        // RN-003 / HU-014: amostra parametrizável, sem hardcode.
        $amostraUsada = $amostra ?? (int) Settings::get('louos.sandbox.amostra_padrao', 50);

        $cenarios = $this->montarCenarios($amostraUsada);

        $impacto = $this->reexecutarSemPersistir(
            fn (): array => $this->reprocessar($domain, $versaoRascunho, $cenarios),
        );

        $relatorio = [
            'dominio' => $domain->value,
            'versao_rascunho' => $versaoRascunho,
            'amostra_usada' => $amostraUsada,
            'total' => $impacto['total'],
            'mudariam' => $impacto['mudariam'],
            'distribuicao' => $impacto['distribuicao'],
            'divergencias' => $impacto['divergencias'],
        ];

        // Registro informativo da simulação (HU-143 fluxo 5) — NÃO é decisão:
        // sem rulesVersion de publicação (RN-001). A publicação é auditada à parte.
        $this->audit->log(
            logName: 'louos',
            event: 'simulacao',
            description: "Simulação de impacto do rascunho {$versaoRascunho} do {$domain->label()}",
            properties: [
                'dominio' => $domain->value,
                'versao_rascunho' => $versaoRascunho,
                'amostra_usada' => $amostraUsada,
                'total' => $relatorio['total'],
                'mudariam' => $relatorio['mudariam'],
                'distribuicao' => $relatorio['distribuicao'],
            ],
            result: 'sucesso',
            rulesVersion: null,
        );

        return $relatorio;
    }

    /**
     * Reprocessa cada cenário com o motor real duas vezes — vigente (sem override)
     * × candidato (override do domínio para a versão rascunho) — e acumula as
     * divergências de veredito consolidado.
     *
     * @param  list<array<string, mixed>>  $cenarios
     * @return array{total: int, mudariam: int, distribuicao: array<string, int>, divergencias: list<array<string, mixed>>}
     */
    private function reprocessar(RuleDomain $domain, string $versaoRascunho, array $cenarios): array
    {
        $total = 0;
        $mudariam = 0;
        $distribuicao = [];
        $divergencias = [];

        foreach ($cenarios as $cenario) {
            $vigente = $this->enquadrar($cenario, $domain, null);
            $candidato = $this->enquadrar($cenario, $domain, $versaoRascunho);

            $total++;

            $de = $vigente->resultado();
            $para = $candidato->resultado();

            if ($de === $para) {
                continue;
            }

            $mudariam++;
            $chave = $de.'→'.$para;
            $distribuicao[$chave] = ($distribuicao[$chave] ?? 0) + 1;

            $divergencias[] = [
                'cenario' => $cenario,
                'resultado_vigente' => $de,
                'resultado_simulado' => $para,
                'motivo_simulado' => $candidato->consolidado['motivo'] ?? null,
            ];
        }

        return compact('total', 'mudariam', 'distribuicao', 'divergencias');
    }

    /**
     * Enquadra um cenário com o MOTOR REAL. `$versaoRascunho` nulo usa a vigente;
     * preenchido aplica o override do domínio simulado (versão específica — 05-03).
     *
     * @param  array<string, mixed>  $cenario
     */
    private function enquadrar(array $cenario, RuleDomain $domain, ?string $versaoRascunho): EnquadramentoResult
    {
        $override = $versaoRascunho === null ? [] : [$domain->value => $versaoRascunho];

        return $this->engine->enquadrar(new EnquadramentoInput(
            area: (float) $cenario['area'],
            cnaePrincipal: (string) $cenario['cnae'],
            territory: $this->territorioComZona((string) $cenario['zona']),
            versoesOverride: $override,
        ));
    }

    /**
     * Monta os cenários da amostra a partir dos dados REAIS: um cenário por CNAE
     * distinto da versão vigente do Quadro 7 (área representativa da faixa) com a
     * zona fixa derivada da versão vigente do Quadro 10. Limitado a `$amostra`.
     *
     * Sem Quadro 7 vigente ou sem zona no Quadro 10 vigente não há cenário
     * comparável (o consolidado seria sempre `pendente`) → amostra vazia.
     *
     * @return list<array<string, mixed>>
     */
    private function montarCenarios(int $amostra): array
    {
        $quadro7 = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $zona = $this->zonaFixa();

        if ($quadro7 === null || $zona === null || $amostra < 1) {
            return [];
        }

        $faixas = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $quadro7->getKey())
            ->orderBy('cnae_code')
            ->orderBy('area_min')
            ->get();

        $cenarios = [];
        $vistos = [];

        foreach ($faixas as $faixa) {
            $cnae = (string) preg_replace('/\D/', '', (string) $faixa->cnae_code);

            if (isset($vistos[$cnae])) {
                continue;
            }

            $vistos[$cnae] = true;

            $cenarios[] = [
                'cnae' => $cnae,
                'cnae_formatado' => $this->formatarCnae($cnae),
                'area' => $this->areaRepresentativa($faixa),
                'zona' => $zona,
            ];

            if (count($cenarios) >= $amostra) {
                break;
            }
        }

        return $cenarios;
    }

    /**
     * Zona fixa dos cenários: a zona da versão vigente do Quadro 10 com mais
     * permissões `permitido` (maximiza cenários além de `pendente`, dando o que
     * comparar); desempate determinístico pelo nome da zona. Null quando não há
     * Quadro 10 vigente ou permissões.
     */
    private function zonaFixa(): ?string
    {
        $quadro10 = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();

        if ($quadro10 === null) {
            return null;
        }

        $permissoes = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $quadro10->getKey())
            ->get();

        if ($permissoes->isEmpty()) {
            return null;
        }

        $permitidoPorZona = [];

        foreach ($permissoes as $permissao) {
            $permitidoPorZona[$permissao->zona] ??= 0;

            if ($permissao->permissao === Quadro10Permissao::Permitido) {
                $permitidoPorZona[$permissao->zona]++;
            }
        }

        $zonas = array_keys($permitidoPorZona);

        usort($zonas, fn (string $a, string $b): int => ($permitidoPorZona[$b] <=> $permitidoPorZona[$a]) ?: ($a <=> $b));

        return $zonas[0];
    }

    /**
     * Área representativa de uma faixa: ponto médio entre o mínimo e o máximo, ou
     * o mínimo + 1 quando não há teto (area_max nula).
     */
    private function areaRepresentativa(LouosQuadro7Faixa $faixa): float
    {
        $min = (float) $faixa->area_min;
        $max = $faixa->area_max === null ? null : (float) $faixa->area_max;

        return $max === null ? $min + 1 : ($min + $max) / 2;
    }

    /**
     * Território sintético da amostra com a zona identificada (espelha o shape de
     * TerritoryResult — Fase 4) para o motor poder ir além de `pendente`. As
     * demais dimensões ficam não encontradas (não afetam o consolidado).
     */
    private function territorioComZona(string $zona): TerritoryResult
    {
        $vazia = [
            'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];

        return new TerritoryResult(
            bairro: $vazia,
            via: $vazia + ['distancia_m' => null],
            zona: [
                'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
                'nome' => $zona,
                'propriedades' => ['NOME' => $zona],
                'motivo' => null,
                'versao_camada' => 'sandbox',
            ],
            lote: $vazia,
            restricoes: [
                'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
                'itens' => [],
                'motivo' => null,
                'versao_camada' => null,
            ],
        );
    }

    /**
     * Código no formato oficial DDDD-D/SS a partir dos dígitos (espelha
     * LouosController::formatCnae / Cnae::formatted_code).
     */
    private function formatarCnae(string $code): string
    {
        return (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $code);
    }

    /**
     * Executa a reexecução do motor numa transação SEMPRE revertida — RN-001:
     * nenhuma escrita do motor durante a simulação persiste (vigente intacta,
     * sem decisão na trilha de auditoria). O relatório é computado em memória e
     * sobrevive ao rollback.
     *
     * @param  callable(): array<string, mixed>  $simulacao
     * @return array<string, mixed>
     */
    private function reexecutarSemPersistir(callable $simulacao): array
    {
        DB::beginTransaction();

        try {
            return $simulacao();
        } finally {
            DB::rollBack();
        }
    }
}
