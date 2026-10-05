<?php

namespace App\Enums;

enum EtapaMotor: string
{
    case LocalizacaoInscricao = 'localizacao_inscricao';
    case PoligonoLote = 'poligono_lote';
    case Zona = 'zona';
    case Via = 'via';
    case Bairro = 'bairro';
    case RegraTerritorial = 'regra_territorial';

    public function servico(): string
    {
        return match ($this) {
            self::LocalizacaoInscricao => 'inscricao_imobiliaria',
            self::PoligonoLote => 'enderecamento_semap',
            self::Zona => 'geoserver_zona',
            self::Via => 'geoserver_via',
            self::Bairro => 'geosalvador_bairro',
            self::RegraTerritorial => 'geoserver_camadas',
        };
    }

    public function ordem(): int
    {
        return match ($this) {
            self::LocalizacaoInscricao => 1,
            self::PoligonoLote => 2,
            self::Zona => 3,
            self::Via => 4,
            self::Bairro => 5,
            self::RegraTerritorial => 6,
        };
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::LocalizacaoInscricao => 'Localização pela inscrição imobiliária',
            self::PoligonoLote => 'Polígono do lote',
            self::Zona => 'Zona urbanística',
            self::Via => 'Classe viária',
            self::Bairro => 'Bairro oficial',
            self::RegraTerritorial => 'Regras territoriais',
        };
    }
}
