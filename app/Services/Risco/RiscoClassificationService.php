<?php

namespace App\Services\Risco;

use App\Enums\Fluxo;
use App\Enums\RuleDomain;
use App\Models\RiskClassification;
use App\Models\RiskCondicionante;
use App\Models\RiskTrigger;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Carbon\CarbonInterface;

/**
 * Motor de classificação de risco (HU-047 a HU-051), espelhando o
 * TerritoryService: dado um CNAE, resolve as versões vigentes (ou da época) das
 * dimensões municipal (Decreto 32.636/2020) e sanitária (VISA) como dimensões
 * SEPARADAS, aplica a reclassificação por condicionante-pergunta, resolve o
 * ENCAMINHAMENTO pela dimensão decisiva via mapa parametrizado (HU-014), aplica
 * os GATILHOS (semi-expresso → análise) e devolve o RiscoResult com a
 * fundamentação legal e as versões — tudo auditado (RN-002).
 *
 * É a DECISÃO DE ROTEAMENTO auditada (elegível a expresso / vai à análise), não
 * o processo expresso (Fases 8/9). A regra de roteamento é DADO (parâmetros e
 * tabela de gatilhos); o motor só APLICA. Sem fachada: CNAE sem classificação
 * vigente, nível ausente no mapa ou gatilho acionado NUNCA é decidido
 * automaticamente — segue para análise com o motivo registrado; nível
 * inexistente jamais é inventado.
 */
class RiscoClassificationService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Classifica o CNAE do input nas duas dimensões, resolve o encaminhamento e
     * audita a decisão com a versão das regras aplicadas (RN-002). Use a versão
     * vigente por padrão, ou a vigente em `$input->data` para reproduzir uma
     * decisão por época (RN-005).
     */
    public function classify(RiscoInput $input): RiscoResult
    {
        // FK lógica para Cnae.code: as classificações guardam o código em
        // dígitos (precedente do import) — normaliza qualquer máscara recebida.
        $cnae = (string) preg_replace('/\D/', '', $input->cnaeCode);

        $municipal = $this->classifyMunicipal($cnae, $input->data);
        $sanitario = $this->classifySanitario($cnae, $input);

        $encaminhamento = $this->resolveEncaminhamento($municipal, $sanitario, $input);

        $versoes = [
            'municipal' => $municipal['versao_regras'] ?? null,
            'sanitario' => $sanitario['versao_regras'] ?? null,
        ];

        $result = new RiscoResult(
            municipal: $municipal,
            sanitario: $sanitario,
            encaminhamento: $encaminhamento,
            fundamentacao: $this->buildFundamentacao($municipal, $sanitario),
            versoes: $versoes,
        );

        $dimensaoDecisiva = $encaminhamento['dimensao_decisiva'];

        $this->audit->log(
            logName: 'risco',
            event: 'classificacao',
            description: "Classificação de risco do CNAE {$input->cnaeCode}",
            properties: [
                'cnae' => $cnae,
                'dimensao_decisiva' => $dimensaoDecisiva,
                'nivel_municipal' => $municipal['nivel'] ?? null,
                'nivel_sanitario_final' => $sanitario['nivel_final'] ?? null,
                'fluxo' => $encaminhamento['fluxo'],
                'gatilhos_acionados' => $encaminhamento['gatilhos_acionados'],
                'versoes' => $versoes,
            ],
            result: 'sucesso',
            rulesVersion: $versoes[$dimensaoDecisiva] ?? null,
        );

        return $result;
    }

    /**
     * Dimensão MUNICIPAL (Decreto 32.636/2020): busca a classificação por
     * (rule_version_id da versão resolvida, cnae_code). Ausente → status
     * 'nao_classificado' (sem nível inventado).
     *
     * @return array<string, mixed>
     */
    private function classifyMunicipal(string $cnae, ?CarbonInterface $date): array
    {
        $version = $this->resolveVersion(RuleDomain::RiscoMunicipal, $date);

        if ($version === null) {
            return $this->dimensaoMunicipalNaoClassificada(null);
        }

        $classification = RiskClassification::query()
            ->where('rule_version_id', $version->getKey())
            ->where('cnae_code', $cnae)
            ->first();

        if ($classification === null) {
            return $this->dimensaoMunicipalNaoClassificada($version->version);
        }

        return [
            'status' => RiscoResult::STATUS_CLASSIFICADO,
            'nivel' => $classification->risco_municipal->value,
            'nivel_label' => $classification->risco_municipal->label(),
            'condicionantes' => $classification->condicionantes ?? [],
            'versao_regras' => $version->version,
        ];
    }

    /**
     * Dimensão SANITÁRIA (VISA), SEPARADA da municipal: busca a classificação e,
     * para cada condicionante-pergunta do CNAE, aplica a reclassificação quando
     * a resposta do requerente bate com `resposta_gatilho` e há
     * `reclassifica_para` (mecanismo "DI"). Ausente → 'nao_classificado'.
     *
     * @return array<string, mixed>
     */
    private function classifySanitario(string $cnae, RiscoInput $input): array
    {
        $version = $this->resolveVersion(RuleDomain::RiscoSanitario, $input->data);

        if ($version === null) {
            return $this->dimensaoSanitariaNaoClassificada(null);
        }

        $classification = SanitaryRiskClassification::query()
            ->where('rule_version_id', $version->getKey())
            ->where('cnae_code', $cnae)
            ->first();

        if ($classification === null) {
            return $this->dimensaoSanitariaNaoClassificada($version->version);
        }

        $nivelOriginal = $classification->risco_sanitario->value;
        $nivelFinal = $nivelOriginal;
        $reclassificado = false;
        $perguntas = [];

        $condicionantes = RiskCondicionante::query()
            ->where('rule_version_id', $version->getKey())
            ->where('cnae_code', $cnae)
            ->get();

        foreach ($condicionantes as $condicionante) {
            $regra = $condicionante->regra_reclassificacao ?? [];
            $resposta = $input->respostasCondicionantes[$condicionante->id]
                ?? $input->respostasCondicionantes[$condicionante->pergunta]
                ?? null;

            $acionou = $resposta !== null
                && array_key_exists('resposta_gatilho', $regra)
                && $resposta === $regra['resposta_gatilho'];

            $reclassificaPara = $regra['reclassifica_para'] ?? null;

            if ($acionou && $reclassificaPara !== null) {
                $nivelFinal = $reclassificaPara;
                $reclassificado = true;
            }

            $perguntas[] = [
                'condicionante_id' => $condicionante->id,
                'pergunta' => $condicionante->pergunta,
                'resposta' => $resposta,
                'acionou' => $acionou,
                'reclassifica_para' => $reclassificaPara,
                'fundamento' => $regra['fundamento'] ?? null,
            ];
        }

        return [
            'status' => RiscoResult::STATUS_CLASSIFICADO,
            'nivel_original' => $nivelOriginal,
            'nivel_final' => $nivelFinal,
            'reclassificado' => $reclassificado,
            'condicionantes_perguntas' => $perguntas,
            'versao_regras' => $version->version,
        ];
    }

    /**
     * Encaminhamento (HU-048/HU-049/HU-050): dimensão decisiva e mapa
     * risk_level→fluxo vêm de parâmetros (HU-014, sem hardcode). Nível decisivo
     * ausente no mapa ou dimensão não classificada degrada para 'analise'
     * (FA-02 seguro). Qualquer gatilho ativo acionado pelo contexto derruba o
     * encaminhamento para 'analise' (semi-expresso), mesmo em baixo risco.
     *
     * @param  array<string, mixed>  $municipal
     * @param  array<string, mixed>  $sanitario
     * @return array<string, mixed>
     */
    private function resolveEncaminhamento(array $municipal, array $sanitario, RiscoInput $input): array
    {
        $dimensaoDecisiva = (string) Settings::get('risco.dimensao_tvl', 'municipal');

        if ($dimensaoDecisiva === 'sanitario') {
            $statusDecisivo = $sanitario['status'];
            $nivelDecisivo = $sanitario['nivel_final'] ?? null;
        } else {
            $statusDecisivo = $municipal['status'];
            $nivelDecisivo = $municipal['nivel'] ?? null;
        }

        if ($statusDecisivo === RiscoResult::STATUS_NAO_CLASSIFICADO || $nivelDecisivo === null) {
            $fluxo = Fluxo::Analise->value;
            $motivo = 'Classificação de risco não parametrizada para o CNAE';
        } else {
            /** @var array<string, string> $mapa */
            $mapa = Settings::get('risco.mapa_encaminhamento', []);
            $fluxo = $mapa[$nivelDecisivo] ?? Fluxo::Analise->value;
            $motivo = $fluxo === Fluxo::Analise->value
                ? "Nível {$nivelDecisivo} ({$dimensaoDecisiva}) encaminhado para análise técnica"
                : "Nível {$nivelDecisivo} ({$dimensaoDecisiva}) elegível ao fluxo expresso";
        }

        $gatilhosAcionados = $this->applyGatilhos($input);

        if ($gatilhosAcionados !== []) {
            $fluxo = Fluxo::Analise->value;
            $motivo = $gatilhosAcionados[0]['motivo'];
        }

        return [
            'fluxo' => $fluxo,
            'dimensao_decisiva' => $dimensaoDecisiva,
            'motivo' => $motivo,
            'gatilhos_acionados' => $gatilhosAcionados,
        ];
    }

    /**
     * Gatilhos semi-expresso (HU-049/HU-051) acionados pelo contexto: cruza os
     * gatilhos ATIVOS (dado parametrizado) com os códigos recebidos no input
     * (ex.: zeis_especial vindo do território da Fase 4 — wiring no EP07). Cada
     * gatilho acionado carrega o motivo auditável. O motor APLICA a regra sobre
     * o contexto; não consulta o território (sem fachada).
     *
     * @return list<array{codigo: string, motivo: string}>
     */
    private function applyGatilhos(RiscoInput $input): array
    {
        if ($input->gatilhosContexto === []) {
            return [];
        }

        $acionados = [];

        foreach (RiskTrigger::ativos()->get() as $trigger) {
            $codigo = $trigger->codigo->value;

            if (in_array($codigo, $input->gatilhosContexto, true)) {
                $acionados[] = [
                    'codigo' => $codigo,
                    'motivo' => (string) $trigger->motivo,
                ];
            }
        }

        return $acionados;
    }

    /**
     * Referências legais reais da decisão: municipal cita o Decreto 32.636/2020
     * e as condicionantes gerais aplicáveis; sanitário cita a base da VISA e o
     * fundamento da condicionante-pergunta efetivamente acionada.
     *
     * @param  array<string, mixed>  $municipal
     * @param  array<string, mixed>  $sanitario
     * @return list<string>
     */
    private function buildFundamentacao(array $municipal, array $sanitario): array
    {
        $referencias = [];

        if ($municipal['status'] === RiscoResult::STATUS_CLASSIFICADO) {
            $referencias[] = 'Decreto Municipal nº 32.636/2020';

            foreach ($municipal['condicionantes'] as $condicionante) {
                $referencias[] = (string) $condicionante;
            }
        }

        if ($sanitario['status'] === RiscoResult::STATUS_CLASSIFICADO) {
            $referencias[] = 'Classificação de risco sanitário (Vigilância Sanitária)';

            foreach ($sanitario['condicionantes_perguntas'] as $pergunta) {
                if (($pergunta['acionou'] ?? false) && ($pergunta['fundamento'] ?? null) !== null) {
                    $referencias[] = (string) $pergunta['fundamento'];
                }
            }
        }

        return array_values($referencias);
    }

    /**
     * Resolve a versão de regra do domínio: vigente por padrão, ou a vigente em
     * `$date` para reprodução por época (espelha TerritoryService::resolveLayer).
     */
    private function resolveVersion(RuleDomain $domain, ?CarbonInterface $date): ?RuleVersion
    {
        if ($date !== null) {
            return RuleVersion::naData($domain, $date)->first();
        }

        return RuleVersion::vigente($domain)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensaoMunicipalNaoClassificada(?string $versao): array
    {
        return [
            'status' => RiscoResult::STATUS_NAO_CLASSIFICADO,
            'nivel' => null,
            'nivel_label' => null,
            'condicionantes' => [],
            'versao_regras' => $versao,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dimensaoSanitariaNaoClassificada(?string $versao): array
    {
        return [
            'status' => RiscoResult::STATUS_NAO_CLASSIFICADO,
            'nivel_original' => null,
            'nivel_final' => null,
            'reclassificado' => false,
            'condicionantes_perguntas' => [],
            'versao_regras' => $versao,
        ];
    }
}
