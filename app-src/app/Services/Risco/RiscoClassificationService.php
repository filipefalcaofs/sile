<?php

namespace App\Services\Risco;

use App\Enums\Fluxo;
use App\Enums\RuleDomain;
use App\Enums\TipoGatilho;
use App\Enums\TipoImovelReconhecimento;
use App\Models\RiskClassification;
use App\Models\RiskTrigger;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Tratamento\TratamentoRamoInput;
use App\Services\Tratamento\TratamentoRamoResolver;
use App\Services\Tratamento\TratamentoRamoResult;
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
    public function __construct(
        private AuditService $audit,
        private DecisionTextCatalog $textos,
        private TratamentoRamoResolver $ramoResolver,
    ) {}

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
        $ramo = $this->resolverTratamento($input);

        if ($ramo?->resolvido()) {
            $municipal = $this->municipalDoRamo($municipal, $ramo);
        }

        $encaminhamento = $this->resolveEncaminhamento($municipal, $sanitario, $input, $ramo);

        $versoes = [
            'municipal' => $municipal['versao_regras'] ?? null,
            'sanitario' => $sanitario['versao_regras'] ?? null,
            'risco_tratamento' => $ramo?->versaoRegra,
        ];

        $result = new RiscoResult(
            municipal: $municipal,
            sanitario: $sanitario,
            encaminhamento: $encaminhamento,
            fundamentacao: $this->buildFundamentacao($municipal),
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
     * Dimensão SANITÁRIA (VISA), SEPARADA da municipal: busca a classificação
     * do CNAE na tabela vigente. Ausente → 'nao_classificado'.
     *
     * SEM reclassificação por condicionante-pergunta: o e-mail SEDUR de
     * 21/09/2026 (item 4) retirou do Viabiliza toda validação de
     * condicionantes da VISA — o nível final é sempre o da tabela.
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

        $nivel = $classification->risco_sanitario->value;

        return [
            'status' => RiscoResult::STATUS_CLASSIFICADO,
            'nivel_original' => $nivel,
            'nivel_final' => $nivel,
            'reclassificado' => false,
            'condicionantes_perguntas' => [],
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
    private function resolveEncaminhamento(array $municipal, array $sanitario, RiscoInput $input, ?TratamentoRamoResult $ramo): array
    {
        $dimensaoDecisiva = (string) Settings::get('risco.dimensao_tvl', 'municipal');
        $nivel = null;

        if ($ramo?->resolvido()) {
            $fluxo = $ramo->fluxo === 'expresso' ? Fluxo::Expresso->value : Fluxo::Analise->value;
            $motivo = $fluxo === Fluxo::Expresso->value
                ? "Nível {$ramo->risco} (planilha vigente) elegível ao fluxo expresso"
                : "Nível {$ramo->risco} (planilha vigente) encaminhado para análise técnica";
            $tll = $ramo->tll;
            $nivel = $ramo->risco;
        } elseif ($dimensaoDecisiva === 'sanitario') {
            $statusDecisivo = $sanitario['status'];
            $nivelDecisivo = $sanitario['nivel_final'] ?? null;
            $tll = null;
            $nivel = $nivelDecisivo;

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
        } else {
            $statusDecisivo = $municipal['status'];
            $nivelDecisivo = $municipal['nivel'] ?? null;
            $tll = null;
            $nivel = $nivelDecisivo;

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
        }

        $gatilhosAcionados = $this->applyGatilhos($input);

        if ($gatilhosAcionados !== []) {
            $fluxo = Fluxo::Analise->value;
            $motivo = $gatilhosAcionados[0]['motivo'];
        }

        return [
            'fluxo' => $fluxo,
            'nivel' => $nivel,
            'dimensao_decisiva' => $ramo?->resolvido() ? 'risco_tratamento' : $dimensaoDecisiva,
            'motivo' => $motivo,
            'gatilhos_acionados' => $gatilhosAcionados,
            'tll' => $tll ?? null,
        ];
    }

    private function resolverTratamento(RiscoInput $input): ?TratamentoRamoResult
    {
        $digitos = (string) preg_replace('/\D/', '', $input->cnaeCode);

        if (strlen($digitos) !== 7) {
            return null;
        }

        return $this->ramoResolver->resolver(new TratamentoRamoInput(
            cnae: substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2),
            respostas: $input->respostasTratamento,
            areaUtilizada: $input->areaUtilizada,
            tipoImovel: $input->tipoImovel,
            data: $input->data,
        ));
    }

    /**
     * Nível municipal dirigido pelo ramo da planilha de tratamento. A versão da
     * dimensão NÃO é sobrescrita (relatório SEDUR 21/09, item 08): o decreto
     * permanece em versao_regras e a planilha já vai em versoes.risco_tratamento
     * — a tela não pode rotular a planilha como "Decreto".
     *
     * @param  array<string, mixed>  $municipal
     * @return array<string, mixed>
     */
    private function municipalDoRamo(array $municipal, TratamentoRamoResult $ramo): array
    {
        $municipal['status'] = RiscoResult::STATUS_CLASSIFICADO;
        $municipal['nivel'] = $ramo->risco;
        $municipal['nivel_label'] = $ramo->risco;

        return $municipal;
    }

    /**
     * Gatilhos semi-expresso (HU-049/HU-051) acionados pelo contexto: cruza os
     * gatilhos ATIVOS (dado parametrizado) com os códigos recebidos no input
     * (ex.: zeis_especial vindo do território da Fase 4 — wiring no EP07). Cada
     * gatilho acionado carrega o motivo auditável. O motor APLICA a regra sobre
     * o contexto; não consulta o território (sem fachada).
     *
     * O gatilho dados_do_processo entra em DOIS casos: tipo que dirige regra
     * (galpão, container, edificação residencial) e tipo DESCONHECIDO — valor
     * enviado pelo REGIN que o catálogo não reconhece vai à análise, nunca a
     * decisão automática (o motor não sabe se aquele imóvel dirige regra).
     * Ausente (o REGIN não enviou nada) NÃO aciona: não há dado a analisar.
     *
     * @return list<array{codigo: string, motivo: string}>
     */
    private function applyGatilhos(RiscoInput $input): array
    {
        $contexto = $input->gatilhosContexto;

        $tipoExigeAnalise = $input->tipoImovel?->dirigeRegra()
            || $input->tipoImovel?->reconhecimento === TipoImovelReconhecimento::Desconhecido;

        if ($tipoExigeAnalise
            && ! in_array(TipoGatilho::DadosDoProcesso->value, $contexto, true)) {
            $contexto[] = TipoGatilho::DadosDoProcesso->value;
        }

        if ($contexto === []) {
            return [];
        }

        $acionados = [];

        foreach (RiskTrigger::ativos()->get() as $trigger) {
            $codigo = $trigger->codigo->value;

            if (in_array($codigo, $contexto, true)) {
                $acionados[] = [
                    'codigo' => $codigo,
                    'motivo' => (string) $trigger->motivo,
                ];
            }
        }

        return $acionados;
    }

    /**
     * Referências legais reais da decisão: municipal cita o decreto vigente
     * (texto administrável — Textos decisórios) e as condicionantes gerais
     * aplicáveis. Nada da VISA entra na fundamentação (relatório SEDUR 21/09,
     * item 09, e e-mail item 4) — o risco sanitário aparece no próprio card.
     *
     * @param  array<string, mixed>  $municipal
     * @return list<string>
     */
    private function buildFundamentacao(array $municipal): array
    {
        $referencias = [];

        if ($municipal['status'] === RiscoResult::STATUS_CLASSIFICADO) {
            $referencias[] = $this->textos->get('base_legal.risco_municipal');

            foreach ($municipal['condicionantes'] as $condicionante) {
                $referencias[] = (string) $condicionante;
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
