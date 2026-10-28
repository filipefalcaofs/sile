<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;
use App\Services\Expresso\SedeEscritorioVirtualGatilho;
use App\Support\Audit\AuditService;

/**
 * Alteração de atividades recebida da JUCEB (evento 244): busca a viabilidade
 * anterior da empresa (processo anterior no Viabiliza, legado ou SEFAZ), compara
 * os CNAEs e grava o resultado no processo.
 * Pedido do portal, que traz a intenção incluir/excluir por CNAE, não passa por aqui.
 */
class ConsultarComparacaoCnaesService
{
    public function __construct(
        private CadeiaViabilidadeAnterior $anterior,
        private ComparacaoCnaesViabilidadeService $comparacao,
        private AuditService $audit,
    ) {}

    public function aplica(ViabilityRequest $request): bool
    {
        return (bool) config('viabiliza.sefaz.consulta.ativa', false)
            && $request->eventos()->where('codigo', SedeEscritorioVirtualGatilho::EVENTO_ALTERACAO_ATIVIDADES)->exists()
            && ! $request->cnaes()->wherePivotNotNull('intencao')->exists()
            && filled($request->company?->cnpj);
    }

    /**
     * Refaz a comparação com os CNAEs atuais do pedido e a viabilidade já
     * consultada (excluídas e mantidas formam a lista dela), sem nova consulta à
     * SEFAZ: a ficha corrige o código sem depender de a SEFAZ estar no ar.
     */
    public function recalcular(ViabilityRequest $request): ?ComparacaoCnaesViabilidade
    {
        if (! is_array($request->comparacao_cnaes)) {
            return null;
        }

        $anterior = ComparacaoCnaesViabilidade::fromArray($request->comparacao_cnaes);

        $resultado = $this->comparacao->comparar(
            $request->cnaes()->pluck('code')->all(),
            $anterior->semViabilidade ? null : [...$anterior->excluidas, ...$anterior->mantidas],
            $anterior->numeroViabilidade,
        );

        $request->forceFill(['comparacao_cnaes' => $resultado->toArray()])->save();

        return $resultado;
    }

    /**
     * @throws SefazConsultaIndisponivelException
     */
    public function executar(ViabilityRequest $request, bool $refazer = false): ?ComparacaoCnaesViabilidade
    {
        if (! $this->aplica($request)) {
            return null;
        }

        if (! $refazer && is_array($request->comparacao_cnaes)) {
            return ComparacaoCnaesViabilidade::fromArray($request->comparacao_cnaes);
        }

        $encontrada = $this->anterior->consultar($request, (string) $request->company->cnpj);
        $viabilidade = $encontrada['viabilidade'] ?? null;

        $resultado = $this->comparacao->comparar(
            $request->cnaes()->pluck('code')->all(),
            $viabilidade?->cnaes,
            $viabilidade?->numero,
        );

        $request->forceFill(['comparacao_cnaes' => $resultado->toArray()])->save();

        $this->audit->log(
            'integracoes',
            'sefaz-consulta-viabilidade',
            "Viabilidade anterior do CNPJ consultada e comparada com o pedido #{$request->id}",
            properties: ['viability_request_id' => $request->id, 'fonte' => $encontrada['fonte'] ?? null] + $resultado->toArray(),
            subject: $request,
        );

        return $resultado;
    }
}
