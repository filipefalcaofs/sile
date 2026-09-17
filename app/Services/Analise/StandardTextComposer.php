<?php

namespace App\Services\Analise;

use App\Models\AnalysisRecord;
use App\Models\StandardText;

/**
 * Esqueleto legal da mala direta (HU-085) sobre os fatos do motor.
 * Nunca inventa quadro: só interpola o que a ficha já registrou.
 */
class StandardTextComposer
{
    /**
     * @return list<string>
     */
    public function blocosParaFicha(AnalysisRecord $ficha): array
    {
        $categoria = $this->categoria($ficha);
        $contexto = $this->contexto($ficha);

        return StandardText::query()
            ->where('active', true)
            ->where('category', $categoria)
            ->orderBy('id')
            ->get()
            ->map(fn (StandardText $texto): string => $this->interpolar((string) $texto->content, $contexto))
            ->filter(fn (string $bloco): bool => $bloco !== '')
            ->values()
            ->all();
    }

    public function paraFicha(AnalysisRecord $ficha): string
    {
        return implode("\n\n", $this->blocosParaFicha($ficha));
    }

    /**
     * @param  array<string, string>  $contexto
     */
    private function interpolar(string $conteudo, array $contexto): string
    {
        return trim(strtr($conteudo, [
            '{{cnae}}' => $contexto['cnae'],
            '{{veredito}}' => $contexto['veredito'],
            '{{zona}}' => $contexto['zona'],
        ]));
    }

    /**
     * @return array{cnae: string, veredito: string, zona: string}
     */
    private function contexto(AnalysisRecord $ficha): array
    {
        $primario = collect($ficha->per_cnae ?? [])->firstWhere('is_primary', true)
            ?? collect($ficha->per_cnae ?? [])->first();

        $cnae = is_array($primario)
            ? (string) ($primario['cnae_formatado'] ?? $primario['cnae'] ?? '')
            : '';

        $snapshot = is_array($ficha->engine_snapshot) ? $ficha->engine_snapshot : [];
        $veredito = is_string($snapshot['consolidado'] ?? null) ? $snapshot['consolidado'] : '';
        $zona = is_string($snapshot['zona'] ?? null) ? $snapshot['zona'] : 'não identificada';

        return [
            'cnae' => $cnae !== '' ? $cnae : 'não informado',
            'veredito' => $veredito !== '' ? $veredito : 'pendente',
            'zona' => $zona,
        ];
    }

    private function categoria(AnalysisRecord $ficha): string
    {
        $consolidado = $ficha->engine_snapshot['consolidado'] ?? null;

        return match ($consolidado) {
            'permitido' => 'deferimento',
            'permitido_com_condicoes' => 'condicionante',
            'nao_permitido' => 'indeferimento',
            default => 'pendencia',
        };
    }
}
