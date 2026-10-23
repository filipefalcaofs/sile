<?php

namespace App\Services\Sefaz;

use App\Models\Cnae;
use App\Models\ViabilityRequest;

/**
 * Comparação dos CNAEs com a viabilidade da SEFAZ no formato da ficha de análise:
 * código com máscara e descrição, quando o CNAE consta da lista do sistema.
 */
class ComparacaoCnaesFicha
{
    /**
     * @return array{numero_viabilidade: ?string, sem_viabilidade: bool, incluidas: list<array{codigo: string, descricao: ?string}>, excluidas: list<array{codigo: string, descricao: ?string}>, mantidas: list<array{codigo: string, descricao: ?string}>, invalidas: list<array{codigo: string, codigo_digitos: string, descricao: ?string}>}|null
     */
    public function dados(ViabilityRequest $request): ?array
    {
        if (! is_array($request->comparacao_cnaes)) {
            return null;
        }

        $comparacao = ComparacaoCnaesViabilidade::fromArray($request->comparacao_cnaes);
        $codigos = [...$comparacao->incluidas, ...$comparacao->excluidas, ...$comparacao->mantidas, ...$comparacao->invalidas];
        $descricoes = Cnae::query()->whereIn('code', $codigos)->where('section_description', '!=', 'REGIN')->pluck('description', 'code');

        $itens = fn (array $lista): array => array_map(fn (string $codigo): array => [
            'codigo' => self::formatar($codigo),
            'descricao' => $descricoes[$codigo] ?? null,
        ], $lista);

        return [
            'numero_viabilidade' => $comparacao->numeroViabilidade,
            'sem_viabilidade' => $comparacao->semViabilidade,
            'incluidas' => $itens($comparacao->incluidas),
            'excluidas' => $itens($comparacao->excluidas),
            'mantidas' => $itens($comparacao->mantidas),
            'invalidas' => array_map(fn (string $codigo): array => [
                'codigo' => self::formatar($codigo),
                'codigo_digitos' => $codigo,
                'descricao' => null,
            ], $comparacao->invalidas),
        ];
    }

    public static function formatar(string $codigo): string
    {
        return (string) preg_replace('/^(\d{4})(\d)(\d{2})$/', '$1-$2/$3', $codigo);
    }
}
