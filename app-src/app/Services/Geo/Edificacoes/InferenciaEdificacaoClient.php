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

        $candidatos = [];

        foreach (is_array($dados['candidatos'] ?? null) ? $dados['candidatos'] : [] as $candidato) {
            $anelCandidato = is_array($candidato) ? $this->anel($candidato['anel'] ?? null) : [];

            if (count($anelCandidato) >= 3) {
                $candidatos[] = [
                    'anel' => $anelCandidato,
                    'score' => is_numeric($candidato['score'] ?? null) ? (float) $candidato['score'] : null,
                    'encosta' => (bool) ($candidato['encosta'] ?? false),
                ];
            }
        }

        return new MascaraIa(
            status: (string) $dados['status'],
            anelPixel: $this->anel($dados['anel'] ?? null),
            score: is_numeric($dados['score'] ?? null) ? (float) $dados['score'] : null,
            modelo: (string) ($dados['modelo'] ?? 'desconhecido'),
            candidatos: $candidatos,
        );
    }

    /**
     * @return list<array{0: float, 1: float}>
     */
    private function anel(mixed $pontos): array
    {
        $validos = array_values(array_filter(
            is_array($pontos) ? $pontos : [],
            fn (mixed $ponto): bool => is_array($ponto) && count($ponto) >= 2 && is_numeric($ponto[0]) && is_numeric($ponto[1]),
        ));

        return array_map(fn (array $ponto): array => [(float) $ponto[0], (float) $ponto[1]], $validos);
    }
}
