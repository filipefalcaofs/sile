<?php

namespace App\Services\Relatorios;

use App\Models\ViabilityRequest;

/**
 * Bairro usado pelos indicadores territoriais: o oficial (camada GeoSalvador)
 * e, na falta dele, o digitado. Vazio ou só espaços é bairro desconhecido
 * (null), nunca um bairro chamado "". A CHAVE (sem espaços nas pontas, em
 * maiúsculas) agrupa e filtra, para que grafias do mesmo bairro não virem
 * linhas diferentes; o ROTULO exibido é a grafia registrada. Acentos não são
 * normalizados.
 */
final class BairroCanonico
{
    public const ROTULO = "coalesce(nullif(trim(viability_requests.bairro_oficial), ''), nullif(trim(viability_requests.address_neighborhood), ''))";

    public const CHAVE = 'upper('.self::ROTULO.')';

    /** O mesmo ROTULO, calculado sobre o model (linhas de exportação). */
    public static function de(ViabilityRequest $solicitacao): ?string
    {
        foreach ([$solicitacao->bairro_oficial, $solicitacao->address_neighborhood] as $bairro) {
            $bairro = trim((string) $bairro);

            if ($bairro !== '') {
                return $bairro;
            }
        }

        return null;
    }
}
