<?php

namespace App\Enums;

/**
 * Tipos de gatilho de risco (categoria semi-expresso) que, quando acionados,
 * derrubam o encaminhamento para análise técnica com motivo auditado
 * (HU-049/HU-051). São os 3 conhecidos hoje (CONTEXT da Fase 6); a lista
 * definitiva é parametrizada na tabela risk_triggers (pendente SEDUR), nunca
 * hardcoded — novos gatilhos entram como dado, não como código.
 */
enum TipoGatilho: string
{
    case EnquadramentoAusente = 'enquadramento_ausente';
    case ZeisEspecial = 'zeis_especial';
    case DadosDoProcesso = 'dados_do_processo';

    // Regra territorial incidente (RT-01 a RT-05 — pacote SEDUR 2026-08-28):
    // a queda do expresso veio de uma regra de lugar (poligonal TAC, ZEM,
    // ZPAM, ABM, ZUE), não do risco da atividade.
    case RegraTerritorial = 'regra_territorial';

    // HU-037 RN-005: o polígono desenhado cai em zona diferente da zona do
    // ponto da inscrição no Cadastro Imobiliário.
    case LocalizacaoDivergente = 'localizacao_divergente';

    // Art. 18, § 2º, da Lei 9.148/2016: o polígono do endereçamento tem porções
    // iguais em duas zonas e a lei não diz qual prevalece.
    case ZonaEmpate = 'zona_empate';

    public function label(): string
    {
        return match ($this) {
            self::EnquadramentoAusente => 'Enquadramento ausente',
            self::ZeisEspecial => 'ZEIS especial',
            self::DadosDoProcesso => 'Dados do processo',
            self::RegraTerritorial => 'Regra territorial',
            self::LocalizacaoDivergente => 'Localização divergente',
            self::ZonaEmpate => 'Zona com porções iguais',
        };
    }
}
