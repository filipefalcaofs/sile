<?php

namespace App\Enums;

/**
 * De onde veio a localização usada no processo. O polígono desenhado tem
 * precedência sobre o ponto; a origem registra qual fonte foi gravada.
 */
enum PropertyLocationSource: string
{
    case Poligono = 'poligono';
    case CadastroImobiliario = 'cadastro_imobiliario';
    case CatalogoSimulacao = 'catalogo_simulacao';
    case Geocodificacao = 'geocodificacao';

    public function label(): string
    {
        return match ($this) {
            self::Poligono => 'Polígono do imóvel',
            self::CadastroImobiliario => 'Cadastro Imobiliário',
            self::CatalogoSimulacao => 'Catálogo da simulação',
            self::Geocodificacao => 'Geocodificação do endereço',
        };
    }
}
