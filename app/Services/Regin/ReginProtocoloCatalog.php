<?php

namespace App\Services\Regin;

use RuntimeException;

/**
 * Catálogo dos protocolos SEDUR usados na homologação do motor. Fonte:
 * database/data/regras-20-08-26/protocolos-sedur.json (extraído dos PDFs
 * de validação). Sem dado pessoal.
 */
class ReginProtocoloCatalog
{
    /**
     * @return list<array<string, mixed>>
     */
    public function todos(): array
    {
        return $this->payload()['protocolos'];
    }

    /**
     * @return array<string, mixed>
     */
    public function porCodigo(string $codigo): array
    {
        foreach ($this->todos() as $protocolo) {
            if (($protocolo['codigo'] ?? null) === $codigo) {
                return $protocolo;
            }
        }

        throw new \InvalidArgumentException("Protocolo de validação desconhecido: {$codigo}.");
    }

    /**
     * @return array{origem: string, protocolos: list<array<string, mixed>>}
     */
    private function payload(): array
    {
        $path = database_path('data/regras-20-08-26/protocolos-sedur.json');

        if (! is_file($path)) {
            throw new RuntimeException("Catálogo de protocolos SEDUR ausente em {$path}.");
        }

        /** @var array{origem: string, protocolos: list<array<string, mixed>>} $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
