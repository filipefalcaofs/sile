<?php

namespace App\Enums;

/**
 * Fonte que forneceu o polígono do lote gravado em address_polygon_geojson.
 * Overture, Microsoft e IA são edificações complementares, usadas só quando
 * as fontes da Prefeitura não acham o lote.
 */
enum PoligonoFonte: string
{
    case Chave = 'chave';
    case Semap = 'semap';
    case Ponto = 'ponto';
    case Overture = 'overture';
    case Microsoft = 'microsoft';
    case Ia = 'ia';

    public function label(): string
    {
        return match ($this) {
            self::Chave => 'Endereçamento por CODLOG e número',
            self::Semap => 'Cadastro municipal (SEMAP)',
            self::Ponto => 'Endereçamento pelo ponto do imóvel',
            self::Overture => 'Edificação da Overture',
            self::Microsoft => 'Edificação da Microsoft',
            self::Ia => 'Edificação gerada por IA',
        };
    }

    public function oficial(): bool
    {
        return in_array($this, [self::Chave, self::Semap, self::Ponto], true);
    }

    /**
     * Texto exibido abaixo do mapa, nomeando a fonte do contorno tracejado.
     */
    public function legenda(): string
    {
        return match ($this) {
            self::Chave, self::Ponto => 'Contorno tracejado: polígono do endereçamento SEDUR.',
            self::Semap => 'Contorno tracejado: polígono do cadastro municipal (SEMAP).',
            self::Overture => 'Contorno tracejado: polígono do Overture.',
            self::Microsoft => 'Contorno tracejado: polígono da Microsoft.',
            self::Ia => 'Contorno tracejado: polígono gerado por IA.',
        };
    }

    /**
     * Atribuição exigida pela licença da fonte complementar.
     */
    public function atribuicao(): ?string
    {
        return match ($this) {
            self::Overture => '© OpenStreetMap contributors, Overture Maps Foundation',
            self::Microsoft => 'Microsoft',
            self::Ia => 'Ortofoto: Prefeitura Municipal de Salvador',
            default => null,
        };
    }
}
