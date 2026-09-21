<?php

namespace App\Http\Resources;

use App\Enums\RuleDomain;
use App\Models\AnalysisRecord;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Services\Analise\JustificativaFundamentadaComposer;
use App\Services\Analise\PerguntaLocalFicha;
use App\Services\Analise\QuadrosFichaChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payload de leitura da ficha de análise (HU-135) para a retaguarda: a revisão
 * vigente, seu status (+rótulo) e a flag `editavel` (false na finalizada — RN-003),
 * o per_cnae com a sugestão do motor (status_sugerido) × a escolha do analista
 * (status_escolhido), as condicionantes, as vagas, o parecer e `finalized_at`.
 * Expõe também a disponibilidade do motor (FA-01 — modo manual). SOMENTE LEITURA;
 * a escrita acontece pelo autosave/finalizar (AnalysisRecordService).
 *
 * Consumido como prop do Inertia: o controller chama ->resolve() para entregar o
 * array puro (sem o wrapper "data" do JsonResource). A página é construída em 10-17.
 *
 * @mixin AnalysisRecord
 */
class AnalysisRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'viability_request_id' => $this->viability_request_id,
            'revision' => $this->revision,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'editavel' => ! $this->isFinalizada(),
            'engine_available' => $this->engine_available,
            'per_cnae' => $this->perCnaeComJustificativaDoMotor(),
            'conditions' => array_values((array) ($this->conditions ?? [])),
            'parking' => $this->parking ?? [],
            'parecer' => $this->parecer,
            'is_virtual_office_hq' => $this->is_virtual_office_hq,
            'analysis_reasons' => $this->motivosSistema(),
            'address_confirmed' => $this->address_confirmed,
            'analyst' => $this->analyst?->name,
            'finalized_at' => $this->finalized_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Fichas antigas (vazias ou só com o motivo curto do motor) recebem o
     * parecer fundamentado na leitura — sem recomputar o veredito.
     *
     * @return list<array<string, mixed>>
     */
    private function perCnaeComJustificativaDoMotor(): array
    {
        $itens = array_values((array) ($this->per_cnae ?? []));
        $composer = app(JustificativaFundamentadaComposer::class);
        $local = app(PerguntaLocalFicha::class);
        $quadros = app(QuadrosFichaChecklist::class);
        $this->resource->loadMissing('viabilityRequest');
        $solicitacao = $this->viabilityRequest;
        $porCnae = is_array($this->engine_snapshot['por_cnae'] ?? null)
            ? $this->engine_snapshot['por_cnae']
            : [];

        return array_map(function (array $item) use ($composer, $porCnae, $local, $solicitacao, $quadros): array {
            if ($solicitacao !== null) {
                $item['pergunta_local'] = $local->para($solicitacao, (string) ($item['cnae'] ?? ''));
            }

            // Valor TLL do exercício corrente, resolvido da tabela de valores
            // (HU-071) pelo código TLL da planilha. Null = pendente (nunca
            // inventado) — a ficha mostra a pendência.
            $item['valor_tll'] = $this->valorTll($item['codigo_tll'] ?? null);

            $consulta = $this->consultaSnapshotDoCnae($porCnae, (string) ($item['cnae'] ?? ''));

            if ($consulta === null) {
                return $item;
            }

            // Checklist ilustrativo dos quadros (Q10 × Q11-A) lido do snapshot.
            $item['quadros'] = $quadros->para($consulta);

            $atual = trim((string) ($item['justificativa'] ?? ''));
            $motivoCurto = trim((string) ($consulta['enquadramento']['consolidado']['motivo']
                ?? $consulta['veredito_locacional']['motivo']
                ?? ''));

            if ($atual !== '' && $atual !== $motivoCurto) {
                return $item;
            }

            $item['justificativa'] = $composer->paraSnapshot($consulta, $item);

            return $item;
        }, $itens);
    }

    /**
     * Valor da TLL do exercício corrente para o código TLL da planilha (HU-071).
     * Null quando não parametrizado — degradação honesta, nunca valor inventado.
     */
    private function valorTll(mixed $codigoTll): ?string
    {
        if (! is_string($codigoTll) || $codigoTll === '') {
            return null;
        }

        $exercicio = (int) now()->year;
        $versaoVigente = RuleVersion::query()
            ->vigente(RuleDomain::TllValores)
            ->where('version', (string) $exercicio)
            ->exists();

        if (! $versaoVigente) {
            return null;
        }

        $tll = TllValor::query()
            ->active()
            ->paraExercicio($codigoTll, $exercicio)
            ->first();

        return $tll === null ? null : (string) $tll->valor;
    }

    /**
     * @param  list<mixed>  $porCnae
     * @return array<string, mixed>|null
     */
    private function consultaSnapshotDoCnae(array $porCnae, string $cnae): ?array
    {
        foreach ($porCnae as $item) {
            if (! is_array($item) || (string) ($item['cnae'] ?? '') !== $cnae) {
                continue;
            }

            return is_array($item['consulta'] ?? null) ? $item['consulta'] : null;
        }

        return null;
    }
}
