<?php

namespace App\Services\Servicos;

use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Analise\ProcessoQueryService;
use Illuminate\Support\Collection;

/**
 * Nome dos serviços exibido no Viabiliza a partir dos eventos da JUCEB, pelo
 * De x Para parametrizado pela SEDUR. Lê a tabela uma vez por requisição.
 */
class ServicoNomenclaturaResolver
{
    /** @var array<string, Collection<int, ServicoNomenclatura>>|null */
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
            'para' => $this->nomePara($evento->nome, $evento->condicao),
            'exibir' => $this->linha($evento->nome, $evento->condicao)?->exibir ?? false,
        ]);

        $exibidos = $mapeados->where('exibir', true);

        return ($exibidos->isNotEmpty() ? $exibidos : $mapeados)
            ->pluck('para')
            ->unique(fn (string $para): string => ServicoNomenclatura::chaveDe($para))
            ->values()
            ->all();
    }

    public function nomePara(string $nomeEvento, ?string $condicao = null): string
    {
        return $this->linha($nomeEvento, $condicao)?->para ?? $nomeEvento;
    }

    /**
     * Opções do filtro "Serviço": uma por nome Para entre os eventos já
     * recebidos, sem diferenciar caixa ou acento. O valor é o nome normalizado,
     * estável mesmo quando um evento novo passa a levar ao mesmo nome.
     *
     * @return list<array{value: string, label: string}>
     */
    public function opcoesDeEvento(): array
    {
        return $this->eventosPorNome()
            ->map(fn (array $grupo, string $chave): array => [
                'value' => ProcessoQueryService::PREFIXO_NOME_SERVICO.$chave,
                'label' => $grupo['label'],
            ])
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Eventos recebidos (código e condição) cujo nome Para tem a chave informada.
     *
     * @return list<array{codigo: string, condicao: ?string}>
     */
    public function eventosDoNome(string $chaveDoNome): array
    {
        return $this->eventosPorNome()->get($chaveDoNome)['eventos'] ?? [];
    }

    /**
     * @return Collection<string, array{label: string, eventos: list<array{codigo: string, condicao: ?string}>}>
     */
    private function eventosPorNome(): Collection
    {
        return ViabilityRequestEvento::query()
            ->select(['codigo', 'nome', 'condicao'])
            ->distinct()
            ->get()
            ->sortBy('codigo', SORT_NATURAL)
            ->groupBy(fn (ViabilityRequestEvento $evento): string => ServicoNomenclatura::chaveDe($this->nomePara($evento->nome, $evento->condicao)))
            ->map(fn (Collection $eventos): array => [
                'label' => $this->nomePara($eventos->first()->nome, $eventos->first()->condicao),
                'eventos' => $eventos
                    ->map(fn (ViabilityRequestEvento $evento): array => ['codigo' => $evento->codigo, 'condicao' => $evento->condicao])
                    ->unique(fn (array $evento): string => $evento['codigo'].'|'.$evento['condicao'])
                    ->values()
                    ->all(),
            ]);
    }

    /**
     * Linha do evento para a condição detectada; sem ela, a linha sem condição
     * ou, por fim, a primeira linha cadastrada do evento.
     */
    private function linha(string $nomeEvento, ?string $condicao = null): ?ServicoNomenclatura
    {
        $this->linhas ??= ServicoNomenclatura::query()->orderBy('id')->get()->groupBy('chave')->all();

        $linhas = $this->linhas[ServicoNomenclatura::chaveDe($nomeEvento)] ?? null;

        if ($linhas === null) {
            return null;
        }

        return $linhas->firstWhere('condicao', (string) $condicao)
            ?? $linhas->firstWhere('condicao', '')
            ?? $linhas->first();
    }
}
