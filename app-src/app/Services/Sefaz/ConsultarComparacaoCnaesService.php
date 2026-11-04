<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;

/**
 * Alteração recebida da JUCEB (atividade, endereço ou outra) de empresa com
 * CNPJ: busca a viabilidade anterior da empresa (processo anterior no
 * Viabiliza, legado Simplifica ou SEFAZ), compara os CNAEs e grava as
 * incluídas, existentes e excluídas no processo.
 * Pedido do portal, que traz a intenção incluir/excluir por CNAE, não passa por aqui.
 */
class ConsultarComparacaoCnaesService
{
    public function __construct(
        private CadeiaViabilidadeAnterior $anterior,
        private ComparacaoCnaesViabilidadeService $comparacao,
        private AuditService $audit,
        private LegadoSimplificaAjustes $legado,
        private LegadoViabilidadeFonte $fonteLegado,
    ) {}

    public function aplica(ViabilityRequest $request): bool
    {
        return ((bool) config('viabiliza.sefaz.consulta.ativa', false) || $this->legado->disponivel())
            && strlen((string) preg_replace('/\D/', '', (string) $request->company?->cnpj)) === 14
            && $request->eAlteracaoDaJunta()
            && ! $request->cnaes()->wherePivotNotNull('intencao')->exists();
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
     * Compara com a viabilidade anterior sem gravar nada no processo.
     *
     * @return array{resultado: ComparacaoCnaesViabilidade, fonte: ?string}
     *
     * @throws SefazConsultaIndisponivelException
     */
    public function compararSemGravar(ViabilityRequest $request): array
    {
        $encontrada = $this->anterior->consultar($request, (string) $request->company?->cnpj);
        $viabilidade = $encontrada['viabilidade'] ?? null;

        return [
            'resultado' => $this->comparacao->comparar(
                $request->cnaes()->pluck('code')->all(),
                $viabilidade?->cnaes,
                $viabilidade?->numero,
            ),
            'fonte' => $encontrada['fonte'] ?? null,
        ];
    }

    /**
     * Null quando não se aplica ou quando o Simplifica falhou: sem a base não há
     * como saber o que foi incluído ou excluído, e gravar "sem base" faria a
     * exclusão passar pelo enquadramento de todas as atividades.
     *
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

        ['resultado' => $resultado, 'fonte' => $fonte] = $this->compararSemGravar($request);

        if ($this->fonteLegado->falhou()) {
            return null;
        }

        return $this->registrar($request, $resultado, $fonte);
    }

    /**
     * Grava no processo a comparação já feita e audita de onde veio a base.
     */
    public function registrar(ViabilityRequest $request, ComparacaoCnaesViabilidade $resultado, ?string $fonte): ComparacaoCnaesViabilidade
    {
        $encontrada = ['fonte' => $fonte];

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
