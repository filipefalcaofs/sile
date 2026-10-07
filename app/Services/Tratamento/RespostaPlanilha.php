<?php

namespace App\Services\Tratamento;

/**
 * Resposta a uma pergunta da planilha de tratamento: Sim/Não (booleano
 * legado), uma opção ou um conjunto de opções. Em pergunta Sim/Não, a opção 1
 * é Sim e a opção 2 é Não, então o booleano antigo continua valendo.
 */
final readonly class RespostaPlanilha
{
    /**
     * @param  list<int>  $opcoes
     */
    private function __construct(
        private array $opcoes,
        private bool|int|array $original,
    ) {}

    /**
     * @param  bool|int|list<int>|self  $valor
     */
    public static function de(mixed $valor): self
    {
        if ($valor instanceof self) {
            return $valor;
        }

        if (is_bool($valor)) {
            return new self([$valor ? 1 : 2], $valor);
        }

        if (is_array($valor)) {
            $opcoes = array_values(array_unique(array_map(intval(...), $valor)));
            sort($opcoes);

            return new self($opcoes, $opcoes);
        }

        return new self([(int) $valor], (int) $valor);
    }

    /**
     * Resposta como o REGIN a entrega: "Sim", "Não", "1,2", "1 e 2" ou "B".
     */
    public static function deTexto(string $texto): ?self
    {
        $limpo = mb_strtolower(trim($texto));

        if ($limpo === '') {
            return null;
        }

        if (in_array($limpo, ['sim', 's', 'true'], true)) {
            return self::de(true);
        }

        if (in_array($limpo, ['não', 'nao', 'n', 'false'], true)) {
            return self::de(false);
        }

        $itens = preg_split('/\s*(?:,|;|\be\b|\/)\s*/u', $limpo) ?: [];
        $opcoes = [];

        foreach ($itens as $item) {
            if (preg_match('/^\d+$/', $item) === 1) {
                $opcoes[] = (int) $item;
            } elseif (preg_match('/^[a-z]$/', $item) === 1) {
                $opcoes[] = ord($item) - ord('a') + 1;
            } else {
                return null;
            }
        }

        return $opcoes === [] ? null : self::de($opcoes);
    }

    /**
     * @return list<int>
     */
    public function opcoes(): array
    {
        return $this->opcoes;
    }

    public function sim(): ?bool
    {
        return match ($this->opcoes) {
            [1] => true,
            [2] => false,
            default => null,
        };
    }

    public function contem(int $opcao): bool
    {
        return in_array($opcao, $this->opcoes, true);
    }

    /**
     * @param  list<int>  $opcoes
     */
    public function algumaDe(array $opcoes): bool
    {
        return array_intersect($this->opcoes, $opcoes) !== [];
    }

    /**
     * @return bool|int|list<int>
     */
    public function paraArmazenar(): bool|int|array
    {
        return $this->original;
    }
}
