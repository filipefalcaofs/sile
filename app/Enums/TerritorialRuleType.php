<?php

namespace App\Enums;

/**
 * Tipo de casamento da regra territorial (RT-01 a RT-05 — pacote normativo
 * SEDUR 2026-08-28). Define COMO a solicitação casa com a regra:
 *
 * - LogradouroCnae: código de logradouro (CODLOG) × lista de CNAEs (RT-01).
 * - PoligonalAnalise: ponto do imóvel dentro da camada geográfica (Vela
 *   Branca, ZEM, ZPAM, ABM trechos 5/6, ZUE).
 * - PoligonalAllowlistExpresso: poligonal com allow-list de endereços —
 *   dentro da poligonal e FORA da lista vai à análise; na lista, segue o
 *   fluxo normal (Itaigara).
 * - ViaAllowlistZona: allow-list de vias (CODLOG) ligada a uma observação do
 *   Quadro 10 — uso sujeito à observação fora das vias listadas não é
 *   permitido (RT-05 a/b).
 */
enum TerritorialRuleType: string
{
    case LogradouroCnae = 'logradouro_cnae';
    case PoligonalAnalise = 'poligonal_analise';
    case PoligonalAllowlistExpresso = 'poligonal_allowlist_expresso';
    case ViaAllowlistZona = 'via_allowlist_zona';

    public function label(): string
    {
        return match ($this) {
            self::LogradouroCnae => 'Logradouro × CNAEs',
            self::PoligonalAnalise => 'Poligonal → análise',
            self::PoligonalAllowlistExpresso => 'Poligonal com allow-list de expresso',
            self::ViaAllowlistZona => 'Allow-list de vias (observação do Quadro 10)',
        };
    }
}
