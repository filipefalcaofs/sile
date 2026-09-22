<?php

namespace App\Services\Realty;

/**
 * Provider INDISPONÍVEL da resolução por inscrição imobiliária: a base de
 * lotes / Cadastro Multifinalitário está PENDENTE da SEDUR (HU-033/HU-106).
 * Sem fonte oficial, NUNCA resolvemos um ponto — degradação honesta, jamais
 * adaptador falso. A Fase 13 troca SÓ este binding pelo provider conveniado
 * (Cadastro/SEFAZ) sem tocar nenhum call site.
 */
class UnavailablePropertyRegistryLookup implements PropertyRegistryLookup
{
    public function resolve(string $inscricao): PropertyRegistryResult
    {
        // Base pendente SEDUR: degrada honestamente, nunca inventa ponto.
        throw new PropertyRegistryUnavailableException($inscricao);
    }
}
