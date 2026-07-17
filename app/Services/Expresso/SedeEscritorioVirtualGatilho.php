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
    /**
     * O gatilho (encaminhar à análise) exige a resposta do requerente "quero ser
     * sede = Sim" E a presença do CNAE gatilho no processo (RN-EV-01).
     */
    public function aplica(ViabilityRequest $request): bool
    {
        return $request->wants_virtual_office_hq && $this->temCnaeGatilho($request);
    }

    /**
     * O processo contém o CNAE gatilho da sede (default 8211-3/00, parametrizável)?
     * Usado independentemente da resposta do requerente pela trava de inscrição no
     * deferimento (RN-EV-03: trava só com CNAE + flag sede confirmada pelo analista).
     */
    public function temCnaeGatilho(ViabilityRequest $request): bool
    {
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
