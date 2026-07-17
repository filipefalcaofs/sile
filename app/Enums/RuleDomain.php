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

    // Quadros da LOUOS (Lei 9.148/2016) como domínios de regra versionada
    // (Fase 5, HU-046): reusam o cabeçalho genérico rule_versions da Fase 6 —
    // cada Quadro tem sua tabela tipada (louos_quadro7_faixas etc.).
    case LouosQuadro7 = 'louos_quadro7';
    case LouosQuadro10 = 'louos_quadro10';
    case LouosQuadro11 = 'louos_quadro11';
    case LouosQuadro11a = 'louos_quadro11a';

    // Lista EV (escritório virtual, RN-EV-05/07): CNAEs permitidos para
    // ABRIGADO, importados por snapshot versionado do endpoint SEDUR.
    case AtividadesEscritorioVirtual = 'atividades_escritorio_virtual';

    public function label(): string
    {
        return match ($this) {
            self::RiscoMunicipal => 'Risco municipal (Decreto 32.636/2020)',
            self::RiscoSanitario => 'Risco sanitário (VISA)',
            self::Condicionante => 'Condicionante',
            self::LouosQuadro7 => 'Quadro 7 da LOUOS (enquadramento por área)',
            self::LouosQuadro10 => 'Quadro 10 da LOUOS (permissão por zona)',
            self::LouosQuadro11 => 'Quadro 11 da LOUOS (condições pela via)',
            self::LouosQuadro11a => 'Quadro 11A da LOUOS (condições complementares pela via)',
            self::AtividadesEscritorioVirtual => 'Atividades permitidas em escritório virtual',
        };
    }

    /**
     * Domínio cuja publicação exige quatro olhos (publicador distinto do autor
     * do rascunho): as dimensões de risco que reclassificam atividade econômica
     * e os Quadros da LOUOS, que decidem a viabilidade locacional.
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::RiscoMunicipal, self::RiscoSanitario => true,
            self::LouosQuadro7, self::LouosQuadro10, self::LouosQuadro11, self::LouosQuadro11a => true,
            self::Condicionante, self::AtividadesEscritorioVirtual => false,
        };
    }
}
