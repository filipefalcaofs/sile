<?php

namespace App\Enums;

/**
 * Resultado da conferência do ponto da inscrição contra a rua do endereço.
 */
enum ConfirmacaoPonto: string
{
    case Confirmado = 'confirmado';
    case Substituido = 'substituido';
    case NaoConfirmado = 'nao_confirmado';
    case NaoVerificado = 'nao_verificado';

    public function label(): string
    {
        return match ($this) {
            self::Confirmado => 'Ponto confirmado pela rua da inscrição',
            self::Substituido => 'Ponto substituído por outra fonte de endereço',
            self::NaoConfirmado => 'Ponto não confirmado por nenhuma fonte',
            self::NaoVerificado => 'Ponto não verificado',
        };
    }
}
