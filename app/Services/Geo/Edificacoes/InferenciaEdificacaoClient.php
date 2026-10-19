<?php

namespace App\Services\Geo\Edificacoes;

use App\Support\Settings;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Cliente do serviço de inferência (docker/footprint-ia). Contrato:
 * POST /segmentar, multipart com a imagem e o ponto em pixels; resposta
 * {status, anel (pixels), score, modelo}.
 */
class InferenciaEdificacaoClient
{
    /**
     * @throws InferenciaIndisponivelException
     */
    public function segmentar(RecorteOrtofoto $recorte, int $x, int $y): MascaraIa
    {
        $url = rtrim((string) Settings::get('integrations.edificacoes.ia_url'), '/');

        if ($url === '') {
            throw new InferenciaIndisponivelException('Serviço de inferência sem URL configurada.');
        }

        try {
            $resposta = Http::timeout((int) config('viabiliza.edificacoes.ia.timeout', 120))
                ->connectTimeout(5)
                ->acceptJson()
                ->attach('imagem', (string) file_get_contents($recorte->caminhoPng), 'recorte.png')
                ->post("{$url}/segmentar", [
                    'x' => $x,
                    'y' => $y,
                    'largura' => $recorte->largura,
                    'altura' => $recorte->altura,
                ]);
        } catch (Throwable $e) {
            throw new InferenciaIndisponivelException($e->getMessage(), previous: $e);
        }

        $dados = $resposta->json();

        if (! $resposta->successful() || ! is_array($dados) || ! in_array($dados['status'] ?? null, [MascaraIa::OK, MascaraIa::SEM_OBJETO], true)) {
            throw new InferenciaIndisponivelException("Serviço de inferência respondeu HTTP {$resposta->status()}.");
        }

        $anel = array_values(array_filter(
            is_array($dados['anel'] ?? null) ? $dados['anel'] : [],
            fn (mixed $ponto): bool => is_array($ponto) && count($ponto) >= 2 && is_numeric($ponto[0]) && is_numeric($ponto[1]),
        ));

        return new MascaraIa(
            status: (string) $dados['status'],
            anelPixel: array_map(fn (array $ponto): array => [(float) $ponto[0], (float) $ponto[1]], $anel),
            score: is_numeric($dados['score'] ?? null) ? (float) $dados['score'] : null,
            modelo: (string) ($dados['modelo'] ?? 'desconhecido'),
        );
    }
}
