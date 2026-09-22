<?php

namespace App\Enums;

/**
 * Reconhecimento do tipo de imóvel enviado pelo REGIN. Só valor reconhecido
 * decide automaticamente; ausente ou desconhecido não vira "não é galpão".
 */
enum TipoImovelReconhecimento: string
{
    case Ausente = 'ausente';
    case Desconhecido = 'desconhecido';
    case DirigeRegra = 'dirige_regra';
    case RamoComum = 'ramo_comum';
}
