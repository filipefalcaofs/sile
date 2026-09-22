<?php

namespace App\Enums;

/**
 * Tipo de camada geográfica (HU-036). isBlockedSource() marca as camadas sem
 * fonte vetorial pública confirmada na pesquisa (zona LOUOS e lote cadastral):
 * é classificação do DADO (alimenta seeder e aviso na UI), não decisão de
 * negócio hardcoded — modeladas como pendente_fonte, nunca polígono inventado.
 */
enum GeoLayerType: string
{
    case Bairro = 'bairro';
    case Zona = 'zona';
    case Via = 'via';
    case Lote = 'lote';
    case Restricao = 'restricao';

    public function label(): string
    {
        return match ($this) {
            self::Bairro => 'Bairro',
            self::Zona => 'Zona urbanística',
            self::Via => 'Via',
            self::Lote => 'Lote',
            self::Restricao => 'Restrição territorial',
        };
    }

    /**
     * Camada sem fonte vetorial pública confirmada (bloqueada até a base
     * oficial da SEDUR/SEFAZ): zona urbanística (Quadro 10 da LOUOS) e lote
     * cadastral por inscrição imobiliária.
     */
    public function isBlockedSource(): bool
    {
        return match ($this) {
            self::Zona, self::Lote => true,
            default => false,
        };
    }
}
