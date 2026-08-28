<?php

namespace App\Enums;

/**
 * Evento que motiva a comunicação à SEFAZ sobre uma condição cadastral de
 * escritório virtual (`Alteração de Endereço` §3.1.3.2/§3.2.2, RN-EV-09):
 * encerramento da sede num endereço, mudança de endereço da sede, ou perda da
 * condição de sede/abrigado por um desfecho da análise (indeferido, cassado,
 * revogado, desativado).
 */
enum SefazNotificationEvent: string
{
    case SedeEncerrada = 'sede_encerrada';
    case SedeMudouEndereco = 'sede_mudou_endereco';
    case SedePerdeuCondicao = 'sede_perdeu_condicao';

    public function label(): string
    {
        return match ($this) {
            self::SedeEncerrada => 'Sede encerrada',
            self::SedeMudouEndereco => 'Sede mudou de endereço',
            self::SedePerdeuCondicao => 'Sede perdeu a condição',
        };
    }
}
