<?php

namespace App\Enums;

/**
 * Domínio de uma regra versionada (HU-019/HU-020/HU-053). Cabeçalho genérico
 * de versão (rule_versions) é tipado por domínio; as tabelas tipadas por
 * domínio (risk_classifications, risk_condicionantes, dimensão sanitária)
 * referenciam a versão. isSensitive() classifica o domínio que exige
 * publicação por quatro olhos — é classificação do DADO, não decisão
 * hardcoded. A Fase 5 acrescenta domínios LOUOS sem tocar a Fase 6.
 */
enum RuleDomain: string
{
    case RiscoMunicipal = 'risco_municipal';
    case RiscoSanitario = 'risco_sanitario';
    case Condicionante = 'condicionante';

    public function label(): string
    {
        return match ($this) {
            self::RiscoMunicipal => 'Risco municipal (Decreto 32.636/2020)',
            self::RiscoSanitario => 'Risco sanitário (VISA)',
            self::Condicionante => 'Condicionante',
        };
    }

    /**
     * Domínio cuja publicação exige quatro olhos (publicador distinto do autor
     * do rascunho): as dimensões de risco que reclassificam atividade econômica.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::RiscoMunicipal, self::RiscoSanitario => true,
            self::Condicionante => false,
        };
    }
}
