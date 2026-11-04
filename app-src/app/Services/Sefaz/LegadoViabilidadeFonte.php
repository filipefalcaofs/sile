<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;
use App\Services\Legado\LegadoViabilidadeClient;
use App\Services\Legado\TvlLegada;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Viabilidade da empresa no legado Simplifica/SAPS, para quem foi licenciado
 * antes do Viabiliza (URL, token e interruptor vêm da tela de APIs): a TVL deferida e ativa de maior validade e os CNAEs da
 * matriz de atividades dela (`GET /v1/provisorias?cnpj=` e `GET /v1/tvls/{n}`).
 *
 * Qualquer falha do legado (rede, 401, 429, 5xx) vale como "sem base": o pedido
 * segue para análise, nunca é decidido sobre uma base que não veio. O resultado,
 * inclusive a ausência de TVL, fica em cache por CNPJ; falha não fica.
 */
class LegadoViabilidadeFonte implements FonteViabilidadeAnterior
{
    private bool $falhou = false;

    public function __construct(
        private LegadoSimplificaAjustes $ajustes,
        private LegadoViabilidadeClient $client,
    ) {}

    public function nome(): string
    {
        return 'legado';
    }

    public function disponivel(): bool
    {
        return $this->ajustes->disponivel();
    }

    /** A última consulta falhou (rede, limite, erro da API) e devolveu null sem saber se há base. */
    public function falhou(): bool
    {
        return $this->falhou;
    }

    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada
    {
        $this->falhou = false;
        $digitos = (string) preg_replace('/\D/', '', $cnpj);
        $chave = "legado:viabilidade:{$digitos}";
        $guardado = Cache::get($chave);

        if (is_array($guardado)) {
            return $guardado === [] ? null : new ViabilidadeVinculada($guardado['numero'], $guardado['cnaes']);
        }

        try {
            $viabilidade = $this->buscar($digitos);
        } catch (Throwable $e) {
            report($e);

            $this->falhou = true;

            return null;
        }

        Cache::put(
            $chave,
            $viabilidade === null ? [] : ['numero' => $viabilidade->numero, 'cnaes' => $viabilidade->cnaes],
            $this->ajustes->cacheTtl(),
        );

        return $viabilidade;
    }

    /**
     * @throws Throwable
     */
    private function buscar(string $cnpj): ?ViabilidadeVinculada
    {
        $vigente = collect($this->client->deferidasPorCnpj($cnpj))
            ->filter(fn (TvlLegada $tvl): bool => $tvl->deferida && $tvl->ativa)
            ->sortByDesc(fn (TvlLegada $tvl): string => sprintf('%s|%018d', $tvl->emissao?->toIso8601String() ?? '', $tvl->numero))
            ->first();

        if ($vigente === null) {
            return null;
        }

        $cnaes = collect($this->client->tvlPorNumero($vigente->numero)?->atividades ?? [])
            ->pluck('codigo')
            ->filter(fn ($codigo): bool => is_string($codigo) && $codigo !== '')
            ->values()
            ->all();

        return $cnaes === [] ? null : new ViabilidadeVinculada((string) $vigente->numero, $cnaes);
    }
}
