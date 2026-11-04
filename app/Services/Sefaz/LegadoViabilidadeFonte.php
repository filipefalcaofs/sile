<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Viabilidade da empresa no legado Simplifica/SAPS, para quem foi licenciado
 * antes do Viabiliza (URL, token e interruptor vêm da tela de APIs): a última Viabilidade deferida e ativa (pela emissão) e os CNAEs da
 * matriz de atividades dela (`GET /v1/tvls?cnpj=`, provisórias e definitivas, e `GET /v1/tvls/{n}`).
 *
 * Qualquer falha do legado (rede, 401, 429, 5xx) vale como "sem base": o pedido
 * segue para análise, nunca é decidido sobre uma base que não veio. O resultado,
 * inclusive a ausência de Viabilidade, fica em cache por CNPJ; falha não fica.
 */
class LegadoViabilidadeFonte implements FonteViabilidadeAnterior
{
    private bool $falhou = false;

    public function __construct(private LegadoSimplificaAjustes $ajustes) {}

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

    /** Esquece a falha anterior, para quem verifica uma operação que talvez nem consulte o Simplifica. */
    public function esquecerFalha(): void
    {
        $this->falhou = false;
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
        $lista = $this->deferidas($cnpj);

        $vigente = collect($lista)
            ->filter(fn (array $tvl): bool => ($tvl['deferida'] ?? false) === true && ($tvl['ativa'] ?? false) === true)
            ->sortByDesc(fn (array $tvl): string => sprintf('%s|%018d', (string) ($tvl['emissao'] ?? ''), (int) ($tvl['numero'] ?? 0)))
            ->first();

        if ($vigente === null) {
            return null;
        }

        $detalhe = $this->get('/v1/tvls/'.$vigente['numero'])['data'] ?? [];
        $cnaes = collect($detalhe['atividades'] ?? [])
            ->pluck('codigo')
            ->filter(fn ($codigo): bool => is_string($codigo) && $codigo !== '')
            ->values()
            ->all();

        return $cnaes === [] ? null : new ViabilidadeVinculada((string) $vigente['numero'], $cnaes);
    }

    /**
     * TVLs deferidas de qualquer tipo (`GET /v1/tvls?cnpj=`). API do legado
     * ainda sem essa rota (404) devolve só as provisórias.
     *
     * @return list<array<string, mixed>>
     *
     * @throws Throwable
     */
    private function deferidas(string $cnpj): array
    {
        try {
            return $this->get('/v1/tvls', ['cnpj' => $cnpj])['data'] ?? [];
        } catch (RequestException $e) {
            if ($e->response->status() !== 404) {
                throw $e;
            }

            return $this->get('/v1/provisorias', ['cnpj' => $cnpj])['data'] ?? [];
        }
    }

    /**
     * @param  array<string, string>  $consulta
     * @return array<string, mixed>
     */
    private function get(string $caminho, array $consulta = []): array
    {
        return Http::withToken($this->ajustes->token())
            ->acceptJson()
            ->timeout($this->ajustes->timeout())
            ->get($this->ajustes->url().$caminho, $consulta)
            ->throw()
            ->json() ?? [];
    }
}
