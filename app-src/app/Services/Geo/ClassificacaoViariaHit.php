<?php

namespace App\Services\Geo;

/**
 * Resultado da consulta da classificação viária LOUOS por CODLOG no GeoServer
 * SEDUR. Sem fachada: `classe` só vem preenchida quando o valor publicado é
 * uma das 7 classes do Quadro 11A; qualquer outro caso carrega o motivo.
 */
final readonly class ClassificacaoViariaHit
{
    public const IDENTIFICADO = 'identificado';

    public const NAO_ENCONTRADO = 'nao_encontrado';

    public const INDISPONIVEL = 'indisponivel';

    public const DESLIGADO = 'desligado';

    public function __construct(
        public string $status,
        public ?string $classe,
        public ?string $classeBruta,
        public ?string $motivo,
    ) {}
}
