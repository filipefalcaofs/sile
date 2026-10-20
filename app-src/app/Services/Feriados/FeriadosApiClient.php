<?php

namespace App\Services\Feriados;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Cliente da FeriadosAPI para o município configurado. Percorre as páginas do
 * ano e devolve só os feriados NACIONAL, ESTADUAL e MUNICIPAL (facultativo fica
 * de fora), com a data já convertida do formato DD/MM/AAAA.
 */
class FeriadosApiClient
{
    private const PER_PAGE = 100;

    private const TYPES = ['nacional', 'estadual', 'municipal'];

    public function isConfigured(): bool
    {
        return filled(config('services.feriados_api.token'));
    }

    /**
     * @return list<array{date: Carbon, name: string, type: string}>
     *
     * @throws RuntimeException quando a API não responde 2xx ou fica inalcançável
     */
    public function holidaysFor(int $year): array
    {
        $holidays = [];
        $page = 1;

        do {
            $body = $this->fetchPage($year, $page);

            foreach ($body['feriados'] ?? [] as $item) {
                $type = strtolower((string) ($item['tipo'] ?? ''));

                if (! in_array($type, self::TYPES, true)) {
                    continue;
                }

                $date = Carbon::createFromFormat('!d/m/Y', (string) $item['data']);

                if ($date === false) {
                    continue;
                }

                $holidays[] = ['date' => $date, 'name' => trim((string) $item['nome']), 'type' => $type];
            }

            $totalPages = (int) ($body['meta']['total_pages'] ?? 1);
            $page++;
        } while ($page <= $totalPages);

        return $holidays;
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchPage(int $year, int $page): array
    {
        $config = config('services.feriados_api');

        try {
            $response = Http::withToken((string) $config['token'])
                ->acceptJson()
                ->timeout((int) $config['timeout'])
                ->get(rtrim((string) $config['base_url'], '/').'/api/v1/feriados/cidade/'.$config['ibge'], [
                    'ano' => $year,
                    'limit' => self::PER_PAGE,
                    'page' => $page,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('FeriadosAPI inalcançável: '.$e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("FeriadosAPI respondeu HTTP {$response->status()} para {$year}.");
        }

        return (array) $response->json();
    }
}
