<?php

namespace App\Services\Louos;

use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Support\Audit\AuditService;
use Illuminate\Database\Eloquent\Builder;

/**
 * Motor de enquadramento da LOUOS (HU-038 a HU-046), espelhando o
 * RiscoClassificationService/TerritoryService: dado um CNAE e uma área, resolve
 * a versão da regra de cada Quadro (vigente / na data / versão específica para
 * o sandbox) e devolve o EnquadramentoResult com cada Quadro como uma dimensão
 * de shape estável, auditando a execução com a versão aplicada (RN-002).
 *
 * Esta etapa (05-03) entrega o Quadro 7 REAL (enquadramento por área → faixa →
 * grupo/subgrupo de uso). Os Quadros 10/11/11A ainda NÃO são avaliados — ficam
 * `indisponivel` com motivo honesto (05-04 substitui pela lógica real) e, por
 * consequência, o consolidado fica `pendente` (05-05 reescreve o consolidar()).
 *
 * Sem fachada: CNAE sem faixa na versão vigente devolve `nao_encontrado` (nunca
 * um grupo inventado); o limite inferior da faixa é inclusivo e area_max nula
 * significa sem teto.
 */
class LouosEnquadramentoService
{
    private const MOTIVO_NAO_AVALIADA = 'Dimensão ainda não avaliada nesta etapa do motor';

    private const MOTIVO_CONSOLIDADO_PENDENTE = 'Enquadramento por área realizado; permissão por zona e condições pela via ainda não avaliadas';

    public function __construct(private AuditService $audit) {}

    /**
     * Enquadra a atividade pela LOUOS. Use a versão vigente por padrão, a
     * vigente em `$input->data` para reproduzir uma decisão por época, ou um
     * `$input->versoesOverride` para o sandbox (HU-143).
     */
    public function enquadrar(EnquadramentoInput $input): EnquadramentoResult
    {
        // cnae_code é guardado em dígitos (precedente do import) — normaliza
        // qualquer máscara recebida para os 7 dígitos da subclasse.
        $cnae = (string) preg_replace('/\D/', '', $input->cnaePrincipal);

        $quadro7 = $this->enquadrarQuadro7($cnae, $input->area, $input);
        $quadro10 = $this->quadro10NaoAvaliado();
        $quadro11 = $this->quadro11NaoAvaliado();
        $quadro11a = $this->quadro11NaoAvaliado();

        $versoes = [
            'quadro7' => $quadro7['versao_regra'] ?? null,
            'quadro10' => $quadro10['versao_regra'] ?? null,
            'quadro11' => $quadro11['versao_regra'] ?? null,
            'quadro11a' => $quadro11a['versao_regra'] ?? null,
        ];

        $consolidado = $this->consolidar($quadro7, $quadro10, $quadro11, $quadro11a);

        $result = new EnquadramentoResult(
            quadro7: $quadro7,
            quadro10: $quadro10,
            quadro11: $quadro11,
            quadro11a: $quadro11a,
            consolidado: $consolidado,
            versoes: $versoes,
        );

        $this->audit->log(
            logName: 'louos',
            event: 'enquadramento',
            description: "Enquadramento LOUOS do CNAE {$input->cnaePrincipal}",
            properties: [
                'cnae' => $cnae,
                'area' => $input->area,
                'quadro7_status' => $quadro7['status'],
                'resultado_consolidado' => $consolidado['resultado'],
                'versoes' => $versoes,
            ],
            result: 'sucesso',
            rulesVersion: $versoes['quadro7'],
        );

        return $result;
    }

    /**
     * Dimensão QUADRO 7 (HU-038): resolve a versão da regra e casa a faixa de
     * área do CNAE (limite inferior inclusivo; area_max nula = sem teto). Sem
     * versão vigente ou sem faixa → `nao_encontrado` (nunca grupo inventado).
     *
     * @return array<string, mixed>
     */
    private function enquadrarQuadro7(string $cnae, float $area, EnquadramentoInput $input): array
    {
        $version = $this->resolveVersion(RuleDomain::LouosQuadro7, $input);

        if ($version === null) {
            return $this->dimNaoEncontrado(
                'Quadro 7 sem versão vigente',
                null,
                ['grupo' => null, 'subgrupo' => null, 'faixa' => null],
            );
        }

        $faixa = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $version->getKey())
            ->where('cnae_code', $cnae)
            ->where('area_min', '<=', $area)
            ->where(function (Builder $query) use ($area): void {
                $query->whereNull('area_max')->orWhere('area_max', '>=', $area);
            })
            ->orderBy('area_min')
            ->first();

        if ($faixa === null) {
            return $this->dimNaoEncontrado(
                'CNAE sem enquadramento parametrizado no Quadro 7 vigente',
                $version->version,
                ['grupo' => null, 'subgrupo' => null, 'faixa' => null],
            );
        }

        return $this->dimIdentificado($version->version, [
            'grupo' => $faixa->grupo,
            'subgrupo' => $faixa->subgrupo,
            'faixa' => [
                'area_min' => $faixa->area_min,
                'area_max' => $faixa->area_max,
            ],
        ]);
    }

    /**
     * Resolução de versão em 3 modos (espelha RiscoClassificationService): um
     * override de versão por domínio (sandbox HU-143) tem precedência; senão a
     * vigente na data informada (reprodução por época); senão a vigente.
     */
    private function resolveVersion(RuleDomain $domain, EnquadramentoInput $input): ?RuleVersion
    {
        $override = $input->versoesOverride[$domain->value] ?? null;

        if ($override !== null) {
            return RuleVersion::versao($domain, $override)->first();
        }

        if ($input->data !== null) {
            return RuleVersion::naData($domain, $input->data)->first();
        }

        return RuleVersion::vigente($domain)->first();
    }

    /**
     * Consolida o veredito (HU-044). Nesta etapa o motor ainda não avalia zona
     * (Quadro 10) nem via (Quadro 11/11A), logo o resultado é honestamente
     * `pendente` — 05-05 reescreve com a lógica final dos 4 Quadros.
     *
     * @param  array<string, mixed>  $quadro7
     * @param  array<string, mixed>  $quadro10
     * @param  array<string, mixed>  $quadro11
     * @param  array<string, mixed>  $quadro11a
     * @return array<string, mixed>
     */
    private function consolidar(array $quadro7, array $quadro10, array $quadro11, array $quadro11a): array
    {
        return [
            'resultado' => ResultadoViabilidade::Pendente->value,
            'fundamentacao' => [],
            'condicionantes' => [],
            'motivo' => self::MOTIVO_CONSOLIDADO_PENDENTE,
        ];
    }

    /**
     * Placeholder honesto do Quadro 10 (permissão por zona) — 05-04 substitui
     * pela resolução real. Mantém o shape do contrato ({permissao,
     * condicionante_ref}) com valores nulos.
     *
     * @return array<string, mixed>
     */
    private function quadro10NaoAvaliado(): array
    {
        return $this->dimIndisponivel(self::MOTIVO_NAO_AVALIADA, null, [
            'permissao' => null,
            'condicionante_ref' => null,
        ]);
    }

    /**
     * Placeholder honesto do Quadro 11/11A (condições pela via) — 05-04
     * substitui pela resolução real. Mantém o shape do contrato ({condicoes}).
     *
     * @return array<string, mixed>
     */
    private function quadro11NaoAvaliado(): array
    {
        return $this->dimIndisponivel(self::MOTIVO_NAO_AVALIADA, null, [
            'condicoes' => null,
        ]);
    }

    /**
     * Dimensão identificada: os campos específicos do Quadro vêm em `$dados`
     * (grupo/subgrupo/faixa no Q7; permissao no Q10; condicoes no Q11/11A).
     * Reutilizável pelo 05-04.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function dimIdentificado(?string $versao, array $dados = []): array
    {
        return [
            'status' => EnquadramentoResult::STATUS_IDENTIFICADO,
            'motivo' => null,
            'versao_regra' => $versao,
        ] + $dados;
    }

    /**
     * Dimensão não encontrada na versão vigente (degradação honesta — nunca
     * inventa dado). Reutilizável pelo 05-04.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function dimNaoEncontrado(string $motivo, ?string $versao, array $dados = []): array
    {
        return [
            'status' => EnquadramentoResult::STATUS_NAO_ENCONTRADO,
            'motivo' => $motivo,
            'versao_regra' => $versao,
        ] + $dados;
    }

    /**
     * Dimensão indisponível (base pendente ou ainda não avaliada nesta etapa):
     * carrega o motivo e força o consolidado a `pendente`. Reutilizável pelo
     * 05-04.
     *
     * @param  array<string, mixed>  $dados
     * @return array<string, mixed>
     */
    private function dimIndisponivel(string $motivo, ?string $versao = null, array $dados = []): array
    {
        return [
            'status' => EnquadramentoResult::STATUS_INDISPONIVEL,
            'motivo' => $motivo,
            'versao_regra' => $versao,
        ] + $dados;
    }
}
