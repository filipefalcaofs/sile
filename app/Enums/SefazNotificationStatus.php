<?php

namespace App\Enums;

/**
 * Status de envio de uma comunicação à SEFAZ (RN-EV-09/EV-10,
 * `Alteração de Endereço` §4.3.3): a falha nunca desfaz o efeito de negócio já
 * concretizado, então o registro fica disponível para reprocessamento em vez de
 * derrubar a transação. Pendente/Falha são reprocessáveis; Enviada é terminal.
 */
enum SefazNotificationStatus: string
{
    case Pendente = 'pendente';
    case Enviada = 'enviada';
    case Falha = 'falha';

    public function label(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Enviada => 'Enviada',
            self::Falha => 'Falha',
        };
    }
}
