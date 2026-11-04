<?php

namespace App\Services\Relatorios\Bi;

use App\Enums\ViabilityRequestStatus;
use App\Http\Resources\ProcessoResource;
use App\Models\AnalysisPendency;
use App\Models\AnalysisRecord;
use App\Models\Inspection;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Services\Analise\TramitacaoProcessoService;
use App\Support\ParecerDecisao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Processos completos para o BI: a ficha como a gestão mostra, todos os campos
 * gravados do processo, da empresa, da decisão, das fichas de análise, das
 * vistorias e dos convites, e a tramitação inteira (mudanças de situação e
 * eventos), em ordem cronológica. Paginado por id crescente.
 */
class BiProcessosCompletos
{
    public const LIMITE_PADRAO = 50;

    public const LIMITE_MAXIMO = 200;

    public function __construct(private readonly TramitacaoProcessoService $tramitacao) {}

    /**
     * @param  array{apos_id?: int|null, limite?: int|null, atualizado_desde?: string|null, data_de?: string|null, data_ate?: string|null, situacao?: string|null}  $parametros
     * @return array{processos: list<array<string, mixed>>, ultimo_id: int|null, tem_mais: bool}
     */
    public function pagina(array $parametros): array
    {
        $limite = min(self::LIMITE_MAXIMO, max(1, (int) ($parametros['limite'] ?? self::LIMITE_PADRAO)));

        $processos = $this->base()
            ->when($parametros['apos_id'] ?? null, fn (Builder $q, $id): Builder => $q->where('id', '>', (int) $id))
            ->when($parametros['atualizado_desde'] ?? null, fn (Builder $q, $desde): Builder => $q->where('updated_at', '>=', Carbon::parse($desde)))
            ->when($parametros['data_de'] ?? null, fn (Builder $q, $de): Builder => $q->where('protocoled_at', '>=', Carbon::parse($de)->startOfDay()))
            ->when($parametros['data_ate'] ?? null, fn (Builder $q, $ate): Builder => $q->where('protocoled_at', '<=', Carbon::parse($ate)->endOfDay()))
            ->when($parametros['situacao'] ?? null, fn (Builder $q, $situacao): Builder => $q->where('status', $situacao))
            ->orderBy('id')
            ->limit($limite + 1)
            ->get();

        $temMais = $processos->count() > $limite;
        $processos = $processos->take($limite);

        return [
            'processos' => $processos->map(fn (ViabilityRequest $p): array => $this->processo($p))->values()->all(),
            'ultimo_id' => $processos->last()?->id,
            'tem_mais' => $temMais,
        ];
    }

    /**
     * Um processo pelo id ou pelo número do protocolo.
     *
     * @return array<string, mixed>|null
     */
    public function um(string $chave): ?array
    {
        $processo = $this->base()
            ->where(fn (Builder $q): Builder => ctype_digit($chave)
                ? $q->where('id', (int) $chave)->orWhere('protocol_number', $chave)
                : $q->where('protocol_number', $chave))
            ->first();

        return $processo === null ? null : $this->processo($processo);
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function base(): Builder
    {
        return ViabilityRequest::query()
            ->whereNotNull('protocoled_at')
            ->where('status', '!=', ViabilityRequestStatus::Rascunho->value)
            ->with([
                'company', 'sector', 'assignedTo', 'serviceType', 'cnaes', 'eventos', 'documents', 'etapas',
                'decision.decidedBy', 'analysisRecords.analyst', 'pendencies.requestedBy', 'expressoQuedas',
                'analysisAttachments', 'transitions.actor', 'analysisStatusTransitions',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function processo(ViabilityRequest $p): array
    {
        $vistorias = Inspection::query()->with('vistoriador')->where('viability_request_id', $p->id)->orderBy('id')->get();

        return [
            'id' => $p->id,
            'protocolo' => $p->protocol_number,
            'ficha' => (new ProcessoResource($p))->resolve(),
            'registro' => $p->attributesToArray(),
            'empresa' => $p->company?->attributesToArray(),
            'cnaes' => $p->cnaes->map(fn (Model $cnae): array => [
                'codigo' => $cnae->code,
                'descricao' => $cnae->description,
                'principal' => (bool) $cnae->pivot->is_primary,
                'intencao' => $cnae->pivot->intencao ?? null,
            ])->values()->all(),
            'servicos_juceb' => $this->linhas($p->eventos),
            'decisao' => $p->decision === null ? null : [
                ...$p->decision->attributesToArray(),
                'decidido_por' => $p->decision->decidedBy?->name,
                'parecer' => ParecerDecisao::de($p->decision),
            ],
            'fichas_analise' => $p->analysisRecords->sortBy('id')->map(fn (AnalysisRecord $ficha): array => [
                ...$ficha->attributesToArray(),
                'analista' => $ficha->analyst?->name,
            ])->values()->all(),
            'vistorias' => $vistorias->map(fn (Inspection $vistoria): array => [
                ...$vistoria->attributesToArray(),
                'vistoriador' => $vistoria->vistoriador?->name,
            ])->values()->all(),
            'convites' => $p->pendencies->sortBy('id')->map(fn (AnalysisPendency $convite): array => [
                ...$convite->attributesToArray(),
                'solicitado_por' => $convite->requestedBy?->name,
            ])->values()->all(),
            'anexos_analise' => $this->linhas($p->analysisAttachments),
            'documentos' => $this->linhas($p->documents),
            'quedas_expresso' => $this->linhas($p->expressoQuedas),
            'etapas_integracao' => $this->linhas($p->etapas),
            'historico_analise' => $this->linhas($p->analysisStatusTransitions),
            'tramitacao' => $this->tramitacao($p),
        ];
    }

    /**
     * A tramitação da tela do processo: mudanças de situação somadas aos eventos
     * (distribuição, convites, vistoria, fichas, documentos, malha fina),
     * da mais antiga para a mais recente.
     *
     * @return list<array<string, mixed>>
     */
    private function tramitacao(ViabilityRequest $p): array
    {
        $situacoes = $p->transitions->sortBy('id')->map(fn (ViabilityRequestTransition $t): array => [
            'tipo' => 'status',
            'titulo' => 'Situação: '.($t->to_status?->label() ?? '—'),
            'de' => $t->from_status?->value,
            'de_rotulo' => $t->from_status?->label(),
            'para' => $t->to_status?->value,
            'para_rotulo' => $t->to_status?->label(),
            'detalhe' => $t->reason,
            'rotulo_publico' => $t->public_label,
            'em' => $t->created_at?->toIso8601String(),
            'ator' => $t->actor?->name,
        ])->values()->all();

        $linha = [...$situacoes, ...$this->tramitacao->eventos($p)];

        usort($linha, fn (array $a, array $b): int => strcmp((string) ($a['em'] ?? ''), (string) ($b['em'] ?? '')));

        return $linha;
    }

    /**
     * @param  iterable<Model>  $modelos
     * @return list<array<string, mixed>>
     */
    private function linhas(iterable $modelos): array
    {
        $linhas = [];

        foreach ($modelos as $modelo) {
            $linhas[] = $modelo->attributesToArray();
        }

        usort($linhas, fn (array $a, array $b): int => ($a['id'] ?? 0) <=> ($b['id'] ?? 0));

        return $linhas;
    }
}
