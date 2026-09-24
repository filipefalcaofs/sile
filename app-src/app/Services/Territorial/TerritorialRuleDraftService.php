<?php

namespace App\Services\Territorial;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TerritorialRule;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Rascunho editável das regras territoriais (domínio `territorial`), espelhando
 * o fluxo dos quadros LOUOS: a edição NUNCA toca a versão vigente — abre (ou
 * reusa) o rascunho do domínio, que nasce como cópia integral da vigente, e
 * aplica a alteração da regra pela chave natural (`codigo`). A publicação é
 * quatro olhos via RuleVersionService (domínio sensível): quem editou não
 * publica. Toda escrita é auditada (RN-002).
 */
class TerritorialRuleDraftService
{
    public function __construct(
        private RuleVersionService $versions,
        private TerritorialRuleCopier $copier,
        private AuditService $audit,
    ) {}

    /**
     * Rascunho aberto do domínio territorial, quando existe (coexiste com a
     * vigente — padrão RuleVersionService).
     */
    public function rascunhoAtual(): ?RuleVersion
    {
        return RuleVersion::query()
            ->where('domain', RuleDomain::Territorial->value)
            ->where('status', RuleVersionStatus::Rascunho->value)
            ->latest('id')
            ->first();
    }

    /**
     * Abre o rascunho do domínio como cópia integral da vigente. Idempotente:
     * com rascunho aberto, devolve o existente (a versão informada é ignorada
     * — a tela a mostra travada).
     */
    public function abrirRascunho(string $version, ?int $autorId): RuleVersion
    {
        $existente = $this->rascunhoAtual();

        if ($existente !== null) {
            return $existente;
        }

        $vigente = RuleVersion::vigente(RuleDomain::Territorial)->first();

        $draft = $this->versions->openDraft(
            RuleDomain::Territorial,
            $version,
            $vigente?->source ?? 'Manutenção das regras territoriais (mantenedores SEDUR)',
            $autorId,
        );

        // O rascunho recém-aberto recebe a cópia da vigente; um rascunho que
        // já existia por (domain, version) NÃO é recopiado (preserva edições).
        if ($draft->wasRecentlyCreated) {
            $this->copier->copy($vigente, $draft);
        }

        return $draft;
    }

    /**
     * Aplica a alteração de UMA regra no rascunho aberto (upsert por código):
     * campos da regra e substituição integral das listas de CNAEs e endereços.
     *
     * @param  array<string, mixed>  $payload
     */
    public function aplicarAlteracao(string $codigo, array $payload, string $version, ?int $autorId): RuleVersion
    {
        return DB::transaction(function () use ($codigo, $payload, $version, $autorId): RuleVersion {
            $draft = $this->abrirRascunho($version, $autorId);

            $regra = TerritorialRule::query()
                ->where('rule_version_id', $draft->getKey())
                ->where('codigo', $codigo)
                ->first();

            $campos = [
                'titulo' => $payload['titulo'],
                'cod_log' => $payload['cod_log'] ?? null,
                'logradouro_nome' => $payload['logradouro_nome'] ?? null,
                'camada' => $payload['camada'] ?? null,
                'camada_filtro' => $payload['camada_filtro'] ?? null,
                'zona_alvo' => $payload['zona_alvo'] ?? null,
                'motivo_analise' => $payload['motivo_analise'] ?? null,
                'decision_text_id' => $payload['decision_text_id'] ?? null,
                'base_legal' => $payload['base_legal'] ?? null,
                'ativo' => (bool) $payload['ativo'],
            ];

            if ($regra === null) {
                $original = TerritorialRule::query()
                    ->where('codigo', $codigo)
                    ->whereHas('ruleVersion', fn ($query) => $query->where('domain', RuleDomain::Territorial->value))
                    ->latest('id')
                    ->first();

                // A edição parte SEMPRE de uma regra existente (tipo/efeito são
                // estruturais e não se editam pela tela) — regra nova entra por
                // carga versionada, não pelo formulário.
                if ($original === null) {
                    throw new DomainException("Regra territorial {$codigo} não encontrada para edição.");
                }

                $regra = TerritorialRule::create([
                    ...$campos,
                    'rule_version_id' => $draft->getKey(),
                    'codigo' => $codigo,
                    'tipo' => $original->tipo,
                    'efeito' => $original->efeito,
                ]);
            } else {
                $regra->update($campos);
            }

            if (array_key_exists('cnaes', $payload)) {
                $regra->cnaes()->delete();

                foreach ($payload['cnaes'] ?? [] as $cnae) {
                    $regra->cnaes()->create(['cnae' => $cnae]);
                }
            }

            if (array_key_exists('addresses', $payload)) {
                $regra->addresses()->delete();

                foreach ($payload['addresses'] ?? [] as $endereco) {
                    $regra->addresses()->create([
                        'rotulo' => $endereco['rotulo'] ?? null,
                        'cod_log' => $endereco['cod_log'] ?? null,
                        'logradouro_nome' => $endereco['logradouro_nome'] ?? null,
                        'numero_porta' => $endereco['numero_porta'] ?? null,
                        'bairro' => $endereco['bairro'] ?? null,
                    ]);
                }
            }

            $this->audit->log(
                logName: 'regras',
                event: 'rascunho-regra-territorial',
                description: "Alteração da regra territorial {$codigo} no rascunho {$draft->version}",
                properties: [
                    'codigo' => $codigo,
                    'versao_rascunho' => $draft->version,
                    'ativo' => (bool) $payload['ativo'],
                    'cnaes' => count($payload['cnaes'] ?? []),
                    'enderecos' => count($payload['addresses'] ?? []),
                ],
                subject: $draft,
                result: 'sucesso',
                rulesVersion: $draft->version,
            );

            return $draft;
        });
    }

    /**
     * Descarta o rascunho aberto (e as regras copiadas/editadas dele) — a
     * vigente permanece intacta.
     */
    public function descartarRascunho(RuleVersion $draft): void
    {
        DB::transaction(function () use ($draft): void {
            TerritorialRule::query()->where('rule_version_id', $draft->getKey())->delete();
            $draft->delete();

            $this->audit->log(
                logName: 'regras',
                event: 'descarte-rascunho-territorial',
                description: "Descarte do rascunho {$draft->version} das regras territoriais",
                properties: ['versao_rascunho' => $draft->version],
                result: 'sucesso',
            );
        });
    }
}
