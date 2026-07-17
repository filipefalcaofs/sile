<?php

namespace App\Services\Expresso;

use App\Models\ViabilityRequest;
use App\Support\Settings;

/**
 * Gatilho de SEDE de escritório virtual (RN-EV-01): processo com o CNAE gatilho
 * (default 8211-3/00) E o requerente respondeu "será sede? = Sim" NÃO conclui no
 * expresso — vai para análise humana. CNAE gatilho é parametrizável.
 */
class SedeEscritorioVirtualGatilho
{
    public function aplica(ViabilityRequest $request): bool
    {
        if (! $request->wants_virtual_office_hq) {
            return false;
        }

        $cnaeGatilho = $this->normalizar((string) Settings::get(
            'analise.escritorio_virtual.cnae_gatilho_sede',
            config('sile.analise.escritorio_virtual.cnae_gatilho_sede', '8211-3/00'),
        ));

        return $request->cnaes->contains(fn ($cnae): bool => $this->normalizar($cnae->code) === $cnaeGatilho);
    }

    /** Só dígitos, para comparar 8211-3/00 == 8211300. */
    private function normalizar(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
