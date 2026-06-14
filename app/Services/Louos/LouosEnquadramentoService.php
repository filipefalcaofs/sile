<?php

namespace App\Services\Louos;

use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
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
    private const MOTIVO_CONSOLIDADO_PENDENTE = 'Enquadramento por área realizado; permissão por zona e condições pela via ainda não avaliadas';

    private const MOTIVO_ZONA_PENDENTE = 'Permissão por zona pendente da base oficial (SEDUR)';

    private const MOTIVO_SEM_ENQUADRAMENTO = 'Sem enquadramento (Quadro 7) não há permissão a verificar';

    private const MOTIVO_QUADRO10_SEM_VERSAO = 'Quadro 10 sem versão vigente';

    private const MOTIVO_ZONA_SEM_REGRA = 'Combinação zona × grupo de uso sem regra no Quadro 10 vigente';

    private const MOTIVO_VIA_PENDENTE = 'Condições pela via dependem da classificação viária LOUOS (pendente SEDUR)';

    private const MOTIVO_VIA_SEM_ATRIBUTO = 'Via identificada, porém sem o atributo de classificação viária LOUOS (pendente SEDUR)';

    private const MOTIVO_VIA_SEM_VERSAO = 'Quadro de condições pela via sem versão vigente';

    private const MOTIVO_VIA_SEM_REGRA = 'Classe viária × grupo de uso sem regra no quadro de via vigente';

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
        $quadro10 = $this->enquadrarQuadro10($quadro7, $input);
        $quadro11 = $this->enquadrarCondicoesVia(RuleDomain::LouosQuadro11, $quadro7, $input);
        $quadro11a = $this->enquadrarCondicoesVia(RuleDomain::LouosQuadro11a, $quadro7, $input);

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
     * Dimensão QUADRO 10 (HU-039): permissão da atividade na zona. O motor
     * RECEBE a zona do território (Fase 4) — não a consulta. Espelha a
     * degradação do TerritoryService::isBlocked, mas sobre o dado recebido:
     *
     * - Sem território OU zona não `identificado` (ex.: base de zoneamento
     *   pendente SEDUR) → `indisponivel` SEM consultar a tabela nem inventar
     *   permissão (anti-fachada).
     * - Sem enquadramento (Quadro 7 não identificado) → `indisponivel`: não há
     *   grupo de uso para verificar permissão.
     * - Com zona e grupo de uso → resolve a versão do Quadro 10 e busca a
     *   permissão por (zona × grupo de uso); ausente → `nao_encontrado`.
     *
     * @param  array<string, mixed>  $quadro7
     * @return array<string, mixed>
     */
    private function enquadrarQuadro10(array $quadro7, EnquadramentoInput $input): array
    {
        $zona = $input->territory?->zona;

        // Degradação honesta (anti-fachada): sem zona identificada o motor não
        // consulta louos_quadro10_permissoes nem inventa permissão.
        if ($zona === null || ($zona['status'] ?? null) !== EnquadramentoResult::STATUS_IDENTIFICADO) {
            return $this->dimIndisponivel(
                $zona['motivo'] ?? self::MOTIVO_ZONA_PENDENTE,
                null,
                ['permissao' => null, 'condicionante_ref' => null, 'base_legal' => null],
            );
        }

        // Pré-condição: sem grupo de uso (Quadro 7) não há o que permitir.
        if (($quadro7['status'] ?? null) !== EnquadramentoResult::STATUS_IDENTIFICADO) {
            return $this->dimIndisponivel(
                self::MOTIVO_SEM_ENQUADRAMENTO,
                null,
                ['permissao' => null, 'condicionante_ref' => null, 'base_legal' => null],
            );
        }

        $version = $this->resolveVersion(RuleDomain::LouosQuadro10, $input);

        if ($version === null) {
            return $this->dimNaoEncontrado(
                self::MOTIVO_QUADRO10_SEM_VERSAO,
                null,
                ['permissao' => null, 'condicionante_ref' => null, 'base_legal' => null],
            );
        }

        $permissao = $this->buscarPermissaoQuadro10(
            $version,
            $this->zonaNome($zona),
            $quadro7['grupo'] ?? null,
            $quadro7['subgrupo'] ?? null,
        );

        if ($permissao === null) {
            return $this->dimNaoEncontrado(
                self::MOTIVO_ZONA_SEM_REGRA,
                $version->version,
                ['permissao' => null, 'condicionante_ref' => null, 'base_legal' => null],
            );
        }

        return $this->dimIdentificado($version->version, [
            'permissao' => $permissao->permissao->value,
            'condicionante_ref' => $permissao->condicionante_ref,
            'base_legal' => $permissao->base_legal,
        ]);
    }

    /**
     * Nome da zona usado na busca do Quadro 10: o `nome` derivado pelo território
     * tem precedência; senão as chaves usuais das propriedades da feição
     * (atributo a confirmar com a base oficial da SEDUR). Null quando ausente —
     * a busca degrada para `nao_encontrado`, nunca inventa zona.
     *
     * @param  array<string, mixed>  $zona
     */
    private function zonaNome(array $zona): ?string
    {
        $nome = $zona['nome'] ?? null;

        if (is_string($nome) && $nome !== '') {
            return $nome;
        }

        /** @var array<string, mixed> $propriedades */
        $propriedades = $zona['propriedades'] ?? [];

        foreach (['ZONA', 'zona', 'SIGLA_ZONA'] as $chave) {
            $valor = $propriedades[$chave] ?? null;

            if (is_string($valor) && $valor !== '') {
                return $valor;
            }
        }

        return null;
    }

    /**
     * Busca a permissão do Quadro 10 por (versão, zona, grupo de uso). Quando o
     * Quadro 7 traz subgrupo, prefere a regra do subgrupo específico, caindo para
     * a regra geral do grupo (subgrupo vazio/nulo) — espelha o seed real.
     */
    private function buscarPermissaoQuadro10(
        RuleVersion $version,
        ?string $zona,
        ?string $grupo,
        ?string $subgrupo,
    ): ?LouosQuadro10Permissao {
        $base = fn (): Builder => LouosQuadro10Permissao::query()
            ->where('rule_version_id', $version->getKey())
            ->where('zona', $zona)
            ->where('grupo_uso', $grupo);

        if ($subgrupo !== null && $subgrupo !== '') {
            $especifica = $base()->where('subgrupo', $subgrupo)->first();

            if ($especifica !== null) {
                return $especifica;
            }
        }

        return $base()
            ->where(function (Builder $query): void {
                $query->whereNull('subgrupo')->orWhere('subgrupo', '');
            })
            ->first();
    }

    /**
     * Dimensões QUADRO 11 e 11A (HU-040/HU-041): condições de instalação pela
     * via, compartilhando a lógica (o `$domain` distingue o quadro). Escopo
     * honesto: a geometria viária existe (Fase 4), mas o ATRIBUTO de
     * classificação viária LOUOS pende SEDUR.
     *
     * - Sem território OU via não `identificado` → `indisponivel` (degradação).
     * - Via identificada SEM o atributo `CLASSE_VIA_LOUOS` (caso atual) →
     *   `indisponivel` SEM inventar a classe (anti-fachada).
     * - Com a classe → resolve a versão do quadro e busca as condições por
     *   (classe viária × grupo de uso); ausente → `nao_encontrado`.
     *
     * @param  array<string, mixed>  $quadro7
     * @return array<string, mixed>
     */
    private function enquadrarCondicoesVia(RuleDomain $domain, array $quadro7, EnquadramentoInput $input): array
    {
        $via = $input->territory?->via;

        // Degradação honesta: sem via identificada não há o que condicionar.
        if ($via === null || ($via['status'] ?? null) !== EnquadramentoResult::STATUS_IDENTIFICADO) {
            return $this->dimIndisponivel(
                $via['motivo'] ?? self::MOTIVO_VIA_PENDENTE,
                null,
                ['classe_via' => null, 'condicoes' => [], 'base_legal' => null],
            );
        }

        // Anti-fachada: sem o atributo de classificação viária LOUOS (pendente
        // SEDUR), o motor NÃO infere a classe a partir da geometria.
        $classeVia = $this->classeViaLouos($via);

        if ($classeVia === null) {
            return $this->dimIndisponivel(
                self::MOTIVO_VIA_SEM_ATRIBUTO,
                null,
                ['classe_via' => null, 'condicoes' => [], 'base_legal' => null],
            );
        }

        $version = $this->resolveVersion($domain, $input);

        if ($version === null) {
            return $this->dimNaoEncontrado(
                self::MOTIVO_VIA_SEM_VERSAO,
                null,
                ['classe_via' => $classeVia, 'condicoes' => [], 'base_legal' => null],
            );
        }

        $condicao = $this->buscarCondicaoVia($version, $classeVia, $quadro7['grupo'] ?? null);

        if ($condicao === null) {
            return $this->dimNaoEncontrado(
                self::MOTIVO_VIA_SEM_REGRA,
                $version->version,
                ['classe_via' => $classeVia, 'condicoes' => [], 'base_legal' => null],
            );
        }

        return $this->dimIdentificado($version->version, [
            'classe_via' => $classeVia,
            'condicoes' => $condicao->condicoes ?? [],
            'base_legal' => $condicao->base_legal,
        ]);
    }

    /**
     * Classe viária da LOUOS lida SOMENTE do atributo oficial da via
     * (`CLASSE_VIA_LOUOS`, a confirmar com a base da SEDUR). Null quando ausente
     * — o motor degrada, nunca infere a classe a partir da geometria.
     *
     * @param  array<string, mixed>  $via
     */
    private function classeViaLouos(array $via): ?string
    {
        $propriedades = $via['propriedades'] ?? [];
        $classe = is_array($propriedades) ? ($propriedades['CLASSE_VIA_LOUOS'] ?? null) : null;

        return is_string($classe) && $classe !== '' ? $classe : null;
    }

    /**
     * Busca a condição de via por (versão, classe viária). Quando o Quadro 7 traz
     * grupo de uso, prefere a regra do grupo específico, caindo para a regra
     * geral da classe (grupo vazio/nulo).
     */
    private function buscarCondicaoVia(RuleVersion $version, string $classeVia, ?string $grupo): ?LouosQuadro11CondicaoVia
    {
        $base = fn (): Builder => LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $version->getKey())
            ->where('classe_via', $classeVia);

        if ($grupo !== null && $grupo !== '') {
            $especifica = $base()->where('grupo_uso', $grupo)->first();

            if ($especifica !== null) {
                return $especifica;
            }
        }

        return $base()
            ->where(function (Builder $query): void {
                $query->whereNull('grupo_uso')->orWhere('grupo_uso', '');
            })
            ->first();
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
