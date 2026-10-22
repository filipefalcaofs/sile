<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Support\Settings;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Galerias do treino da IA de contorno de lote, geradas pelas ferramentas de
 * scripts/treino-ia no volume. Só lê storage/app/private/treino-ia/galerias e
 * só existe com o parâmetro features.treino_ia_galeria ligado.
 */
class TreinoIaGaleriaController extends Controller
{
    private const PASTA = 'treino-ia/galerias';

    private const TIPOS = ['html' => 'text/html; charset=UTF-8', 'json' => 'application/json', 'png' => 'image/png'];

    public function index(): Response
    {
        abort_unless(Settings::enabled('treino_ia_galeria'), 404);

        $execucoes = collect(Storage::disk('local')->directories(self::PASTA))
            ->map(fn (string $pasta): ?array => $this->execucao(basename($pasta)))
            ->filter()
            ->sortByDesc('execucao')
            ->values()
            ->all();

        return Inertia::render('gestao/treino-ia/index', ['execucoes' => $execucoes]);
    }

    public function arquivo(string $execucao, ?string $arquivo = null): BinaryFileResponse
    {
        abort_unless(Settings::enabled('treino_ia_galeria'), 404);
        abort_unless(preg_match('/^[A-Za-z0-9._-]+$/', $execucao) === 1 && ! str_starts_with($execucao, '.'), 404);

        $base = realpath(Storage::disk('local')->path(self::PASTA.'/'.$execucao));
        $caminho = $base === false ? false : realpath($base.DIRECTORY_SEPARATOR.($arquivo ?: 'index.html'));
        $extensao = $caminho === false ? '' : strtolower(pathinfo($caminho, PATHINFO_EXTENSION));

        abort_unless($caminho !== false && str_starts_with($caminho, $base.DIRECTORY_SEPARATOR) && isset(self::TIPOS[$extensao]) && is_file($caminho), 404);

        return response()->file($caminho, ['Content-Type' => self::TIPOS[$extensao]]);
    }

    /**
     * @return array{execucao: string, gerada_em: string|null, lotes: int, variantes: list<string>, mediana_iou_por_variante: array<string, float|null>}|null
     */
    private function execucao(string $nome): ?array
    {
        $disco = Storage::disk('local');
        $arquivo = self::PASTA."/{$nome}/resumo.json";

        if (! $disco->exists($arquivo)) {
            return null;
        }

        $resumo = json_decode((string) $disco->get($arquivo), true) ?: [];
        $variantes = array_values($resumo['ordem_variantes'] ?? array_keys($resumo['variantes'] ?? []));

        return [
            'execucao' => $nome,
            'gerada_em' => date('d/m/Y H:i', $disco->lastModified($arquivo)),
            'lotes' => (int) ($resumo['lotes'] ?? 0),
            'variantes' => $variantes,
            'mediana_iou_por_variante' => collect($variantes)
                ->mapWithKeys(fn (string $v): array => [$v => $resumo['variantes'][$v]['iou']['mediana'] ?? null])
                ->all(),
        ];
    }
}
