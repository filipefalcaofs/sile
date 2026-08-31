<?php

namespace App\Services\EscritorioVirtual;

use App\Models\VirtualOfficeActivityCnae;
use App\Support\Settings;

/**
 * Atividades que uma SEDE de escritorio virtual pode exercer (RN-EV-05c):
 * {CNAE gatilho} uniao Anexo A. O CNAE gatilho (default 8211-3/00,
 * parametrizavel) caracteriza a sede e por isso nao figura no Anexo A —
 * precisa ser excluido da conferencia, senao toda sede seria indeferida
 * pelo proprio CNAE que a define (SEDUR 2026-08-31). O parametro e lido
 * pelo mesmo caminho que SedeEscritorioVirtualGatilho::temCnaeGatilho()
 * usa, ja que aqui so ha codigos de CNAE, sem um ViabilityRequest.
 */
class SedeAtividadesResolver
{
    /**
     * Um codigo e permitido a sede se for o CNAE gatilho ou constar do
     * Anexo A na versao vigente do dominio.
     */
    public function permitida(string $cnaeCode): bool
    {
        if ($this->normalizar($cnaeCode) === $this->cnaeGatilho()) {
            return true;
        }

        return VirtualOfficeActivityCnae::permitidoNoAnexo($cnaeCode, VirtualOfficeActivityCnae::ANEXO_A);
    }

    /**
     * Codigos que NAO podem ser exercidos pela sede, na ordem e na grafia
     * de entrada. Lista vazia = todas permitidas.
     *
     * @param  iterable<string>  $cnaeCodes
     * @return array<int, string>
     */
    public function naoPermitidos(iterable $cnaeCodes): array
    {
        $naoPermitidos = [];

        foreach ($cnaeCodes as $cnaeCode) {
            if (! $this->permitida($cnaeCode)) {
                $naoPermitidos[] = $cnaeCode;
            }
        }

        return $naoPermitidos;
    }

    /** CNAE gatilho da sede, normalizado a digitos (mesmo caminho de SedeEscritorioVirtualGatilho). */
    private function cnaeGatilho(): string
    {
        return $this->normalizar((string) Settings::get(
            'analise.escritorio_virtual.cnae_gatilho_sede',
            config('sile.analise.escritorio_virtual.cnae_gatilho_sede', '8211-3/00'),
        ));
    }

    /** Só dígitos, para comparar 8211-3/00 == 8211300. */
    private function normalizar(string $code): string
    {
        return preg_replace('/\D/', '', $code) ?? '';
    }
}
