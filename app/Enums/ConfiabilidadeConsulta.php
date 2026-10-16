<?php

namespace App\Enums;

/**
 * Confiabilidade do resultado da consulta prévia de viabilidade. Só `Conclusiva`
 * carrega veredito locacional: com dado faltando (`Incompleta`) ou quando o motor
 * não consegue decidir (`Inconclusiva`), a consulta não afirma permitido nem
 * não permitido.
 */
enum ConfiabilidadeConsulta: string
{
    case Conclusiva = 'conclusiva';
    case Incompleta = 'incompleta';
    case Inconclusiva = 'inconclusiva';

    public function label(): string
    {
        return match ($this) {
            self::Conclusiva => 'Resultado conclusivo',
            self::Incompleta => 'Consulta incompleta',
            self::Inconclusiva => 'Não foi possível concluir',
        };
    }
}
