<?php

namespace App\Enums;

/**
 * Resultado de uma tentativa de obter o polígono do lote numa fonte.
 */
enum StatusResolucaoPoligono: string
{
    case Encontrado = 'encontrado';
    case NaoEncontrado = 'nao_encontrado';
    case Indisponivel = 'indisponivel';
    case Pendente = 'pendente';
    case NaoResolvido = 'nao_resolvido';
    case Desligado = 'desligado';
}
