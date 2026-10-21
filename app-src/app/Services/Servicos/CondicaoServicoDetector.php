<?php

namespace App\Services\Servicos;

use App\Enums\CondicaoServico;
use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequest;

/**
 * Escolhe a linha condicional do De x Para comparando o processo com o último
 * processo da mesma empresa no Viabiliza. Sem processo anterior, ou sem
 * diferença, não há condição e vale a primeira linha do evento.
 */
class CondicaoServicoDetector
{
    public function detectar(ViabilityRequest $processo, string $nomeEvento): ?CondicaoServico
    {
        $condicoes = ServicoNomenclatura::query()
            ->where('chave', ServicoNomenclatura::chaveDe($nomeEvento))
            ->where('condicao', '!=', '')
            ->pluck('condicao')
            ->map(fn (string $condicao): ?CondicaoServico => CondicaoServico::tryFrom($condicao))
            ->filter();

        if ($condicoes->isEmpty() || $processo->company_id === null) {
            return null;
        }

        $anterior = ViabilityRequest::query()
            ->where('company_id', $processo->company_id)
            ->where('id', '<', $processo->id)
            ->latest('id')
            ->first();

        if ($anterior === null) {
            return null;
        }

        return $condicoes->contains(fn (CondicaoServico $condicao): bool => $condicao->comparaCnaes())
            ? $this->pelosCnaes($processo, $anterior)
            : $this->peloEndereco($processo, $anterior);
    }

    private function pelosCnaes(ViabilityRequest $processo, ViabilityRequest $anterior): CondicaoServico
    {
        $atuais = $processo->cnaes()->pluck('cnaes.id')->all();
        $excluidos = $anterior->cnaes()->pluck('cnaes.id')->diff($atuais);

        return $excluidos->isNotEmpty() ? CondicaoServico::CnaeExcluido : CondicaoServico::CnaeIncluido;
    }

    private function peloEndereco(ViabilityRequest $processo, ViabilityRequest $anterior): ?CondicaoServico
    {
        if ($this->mudou($processo, $anterior, ['cod_log'], ignorarVazio: true)
            || $this->mudou($processo, $anterior, ['address_number', 'address_neighborhood'])) {
            return CondicaoServico::EnderecoLocalizacao;
        }

        if ($this->mudou($processo, $anterior, ['address_complement', 'address_zip'])) {
            return CondicaoServico::EnderecoEspaco;
        }

        return null;
    }

    /**
     * Compara sem caixa, acento ou espaço extra. Com ignorarVazio, campo vazio
     * de um dos lados não conta: o processo antigo pode não ter o codlog.
     *
     * @param  list<string>  $campos
     */
    private function mudou(ViabilityRequest $processo, ViabilityRequest $anterior, array $campos, bool $ignorarVazio = false): bool
    {
        foreach ($campos as $campo) {
            $atual = ServicoNomenclatura::chaveDe((string) $processo->getAttribute($campo));
            $antes = ServicoNomenclatura::chaveDe((string) $anterior->getAttribute($campo));

            if ($ignorarVazio && ($atual === '' || $antes === '')) {
                continue;
            }

            if ($atual !== $antes) {
                return true;
            }
        }

        return false;
    }
}
