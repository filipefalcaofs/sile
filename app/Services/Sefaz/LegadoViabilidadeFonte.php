<?php

namespace App\Services\Sefaz;

use App\Models\ViabilityRequest;

/**
 * Viabilidade da empresa no sistema legado (SAPS), para quem foi licenciado antes
 * do Viabiliza. A API de consulta ainda não existe: a fonte fica indisponível e
 * a cadeia a ignora até haver integração.
 */
class LegadoViabilidadeFonte implements FonteViabilidadeAnterior
{
    public function nome(): string
    {
        return 'legado';
    }

    public function disponivel(): bool
    {
        return false;
    }

    public function consultar(ViabilityRequest $processo, string $cnpj): ?ViabilidadeVinculada
    {
        return null;
    }
}
