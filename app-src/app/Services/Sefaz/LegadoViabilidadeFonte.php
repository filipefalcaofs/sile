<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
    public function __construct(private LegadoSimplificaAjustes $ajustes) {}

    public function nome(): string
    {
        return 'legado';
    }

    public function disponivel(): bool
    {
        return $this->ajustes->disponivel();
    }

    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada
    {
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
        $lista = $this->get('/v1/provisorias', ['cnpj' => $cnpj])['data'] ?? [];

        $vigente = collect($lista)
            ->filter(fn (array $tvl): bool => ($tvl['deferida'] ?? false) === true && ($tvl['ativa'] ?? false) === true)
            ->sortByDesc(fn (array $tvl): string => sprintf('%s|%018d', (string) ($tvl['validade'] ?? ''), (int) ($tvl['numero'] ?? 0)))
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
