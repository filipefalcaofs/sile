<?php

namespace App\Enums;

/**
 * Domínio de uma regra versionada (HU-018/HU-019/HU-051). Cabeçalho genérico
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
    // (Fase 5, HU-044): reusam o cabeçalho genérico rule_versions da Fase 6 —
    // cada Quadro territorial tem sua tabela tipada.
    case LouosQuadro10 = 'louos_quadro10';
    case LouosQuadro11a = 'louos_quadro11a';

    // Lista EV (escritório virtual, RN-EV-05/07): CNAEs permitidos para
    // ABRIGADO, importados por snapshot versionado do endpoint SEDUR.
    case AtividadesEscritorioVirtual = 'atividades_escritorio_virtual';
    case RiscoTratamento = 'risco_tratamento';

    // Tabela de valores TLL por exercício (Lei 7.186/2006, Anexo IV) — dado
    // versionado que define valor de tributo (DAM). Sensível: quatro olhos.
    case TllValores = 'tll_valores';

    // Regras territoriais (RT-01 a RT-05 — pacote normativo SEDUR 2026-08-28):
    // restrições por logradouro × CNAE (art. 172-A), TACs de loteamento,
    // zonas que sempre vão à análise (ZEM/ZPAM/ABM/ZUE) e allow-lists de via
    // das observações do Quadro 10. Sensível: decide viabilidade locacional.
    case Territorial = 'territorial';

    public function label(): string
    {
        return match ($this) {
            self::RiscoMunicipal => 'Risco municipal (Decreto 32.636/2020)',
            self::RiscoSanitario => 'Risco sanitário (VISA)',
            self::Condicionante => 'Condicionante',
            self::LouosQuadro10 => 'Quadro 10 da LOUOS (permissão por zona)',
            self::LouosQuadro11a => 'Quadro 11A da LOUOS (condições complementares pela via)',
            self::AtividadesEscritorioVirtual => 'Atividades permitidas em escritório virtual',
            self::RiscoTratamento => 'Planilha de regras de tratamento (20.08.26)',
            self::TllValores => 'Tabela de valores TLL por exercício',
            self::Territorial => 'Regras territoriais (logradouro, TAC, zonas especiais)',
        };
    }

    /**
     * Domínio cuja publicação exige quatro olhos (publicador distinto do autor
     * do rascunho): as dimensões de risco que reclassificam atividade econômica,
     * os Quadros da LOUOS, que decidem a viabilidade locacional, e os Anexos
     * A/B do Decreto 35.062/2021, que definem quem constitui sede/abrigado de
     * escritório virtual — publicar anexo bloqueia/libera constituição de sede
     * (decisório; auditoria, ponto G7).
     */
    public function isSensitive(): bool
    {
        return match ($this) {
            self::RiscoMunicipal, self::RiscoSanitario => true,
            self::LouosQuadro10, self::LouosQuadro11a => true,
            self::AtividadesEscritorioVirtual => true,
            self::RiscoTratamento => true,
            self::TllValores => true,
            self::Territorial => true,
            self::Condicionante => false,
        };
    }
}
