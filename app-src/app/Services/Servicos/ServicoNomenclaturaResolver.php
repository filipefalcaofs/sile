<?php

namespace App\Services\Servicos;

use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Analise\ProcessoQueryService;

/**
 * Nome dos serviços exibido no Viabiliza a partir dos eventos da JUCEB, pelo
 * De x Para parametrizado pela SEDUR. Lê a tabela uma vez por requisição.
 */
class ServicoNomenclaturaResolver
{
    /** @var array<string, ServicoNomenclatura>|null */
    private ?array $linhas = null;

    /**
     * Serviços do processo: o Para de cada evento, sem repetir. Com mais de um
     * evento, ficam só os marcados como Exibir; sem nenhum marcado, todos. Sem
     * eventos, o tipo de serviço interno.
     *
     * @return list<string>
     */
    public function servicos(ViabilityRequest $processo): array
    {
        $processo->loadMissing(['eventos', 'serviceType:id,name']);

        if ($processo->eventos->isEmpty()) {
            return array_values(array_filter([$processo->serviceType?->name]));
        }

        $mapeados = $processo->eventos->map(fn ($evento): array => [
            'para' => $this->nomePara($evento->nome),
            'exibir' => $this->linha($evento->nome)?->exibir ?? false,
        ]);

        $exibidos = $mapeados->where('exibir', true);

        return ($exibidos->isNotEmpty() ? $exibidos : $mapeados)
            ->pluck('para')
            ->unique()
            ->values()
            ->all();
    }

    public function nomePara(string $nomeEvento): string
    {
        return $this->linha($nomeEvento)?->para ?? $nomeEvento;
    }

    /**
     * Opções do filtro "Serviço": uma por nome Para entre os eventos já
     * recebidos, com os códigos de todos os eventos que levam a esse nome.
     *
     * @return list<array{value: string, label: string}>
     */
    public function opcoesDeEvento(): array
    {
        return ViabilityRequestEvento::query()
            ->select(['codigo', 'nome'])
            ->distinct()
            ->get()
            ->groupBy(fn (ViabilityRequestEvento $evento): string => $this->nomePara($evento->nome))
            ->map(fn ($eventos, string $para): array => [
                'value' => ProcessoQueryService::PREFIXO_EVENTO.$eventos->pluck('codigo')->unique()->sort(SORT_NATURAL)->implode(','),
                'label' => $para,
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    private function linha(string $nomeEvento): ?ServicoNomenclatura
    {
        $this->linhas ??= ServicoNomenclatura::query()->get()->keyBy('chave')->all();

        return $this->linhas[ServicoNomenclatura::chaveDe($nomeEvento)] ?? null;
    }
}
