<?php

namespace App\Services\Integracao;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\ResponseInterface;

/**
 * Serviços legados (GeoServer da SEDUR) respondem texto em ISO-8859-1. Sem a
 * conversão, json_decode devolve null e o Postgres recusa o corpo no log.
 */
class NormalizaCharsetResposta
{
    public function __invoke(ResponseInterface $resposta): ResponseInterface
    {
        $tipo = $resposta->getHeaderLine('Content-Type');

        if (! preg_match('#json|text/|xml#i', $tipo)) {
            return $resposta;
        }

        $corpo = (string) $resposta->getBody();

        if ($corpo === '' || mb_check_encoding($corpo, 'UTF-8')) {
            return $resposta;
        }

        $convertido = mb_convert_encoding($corpo, 'UTF-8', 'ISO-8859-1');
        $tipoUtf8 = preg_replace('/;\s*charset=[^;]*/i', '', $tipo).'; charset=UTF-8';

        return $resposta
            ->withBody(Utils::streamFor($convertido))
            ->withHeader('Content-Type', $tipoUtf8)
            ->withHeader('Content-Length', (string) strlen($convertido));
    }
}
