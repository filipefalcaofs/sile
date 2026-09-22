<?php

namespace App\Enums;

/**
 * Severidade de um alerta de abuso/fraude (HU-149). Tem ordenação (peso) para
 * comparar com o parâmetro `abuso.severidade_malha_fina`: alertas com severidade
 * >= o limiar configurado são encaminhados à malha fina (nunca punidos — RN-001).
 */
enum AbuseSeverity: string
{
    case Baixa = 'baixa';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::Baixa => 'Baixa',
            self::Media => 'Média',
            self::Alta => 'Alta',
        };
    }

    /**
     * Peso para ordenação/comparação (Baixa < Média < Alta).
     */
    public function weight(): int
    {
        return match ($this) {
            self::Baixa => 1,
            self::Media => 2,
            self::Alta => 3,
        };
    }

    /**
     * Esta severidade é pelo menos tão grave quanto o limiar informado?
     * Base da decisão de encaminhar à malha fina (severity >= limiar).
     */
    public function isAtLeast(self $threshold): bool
    {
        return $this->weight() >= $threshold->weight();
    }
}
