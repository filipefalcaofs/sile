<?php

namespace App\Services\Geo;

use App\Enums\ConfirmacaoPonto;
use App\Enums\PropertyLocationSource;
use App\Support\Settings;

/**
 * Confere se o ponto devolvido pelo Cadastro Imobiliário está perto da rua do
 * endereço da inscrição (eixo da camada viária da SEDUR, pelo CODLOG). Fora do
 * limite, procura um ponto melhor por endereço; se nenhuma fonte confirmar, o
 * ponto da SEFAZ segue marcado como não confirmado.
 */
class ValidadorPontoInscricao
{
    public function __construct(
        private GeoServerEixoViarioClient $eixo,
        private PontoAlternativoResolver $alternativos,
    ) {}

    public function validar(
        float $lat,
        float $lng,
        ?string $codLog,
        ?string $numero,
        ?string $enderecoParaGeocode,
        string $inscricao,
    ): ResultadoPonto {
        $semConferencia = new ResultadoPonto(ConfirmacaoPonto::NaoVerificado, $lat, $lng, PropertyLocationSource::CadastroImobiliario);

        if (! Settings::enabled('validacao_ponto_inscricao') || $codLog === null || trim($codLog) === '') {
            return $semConferencia;
        }

        $eixo = $this->eixo->consultar($codLog);

        if (! $eixo->identificado()) {
            return $semConferencia;
        }

        $distanciaOriginal = DistanciaGeografica::ateLinhas($lat, $lng, $eixo->linhas);

        if ($distanciaOriginal === null) {
            return $semConferencia;
        }

        $limite = (float) config('viabiliza.geo.validacao_ponto.distancia_maxima_m', 150);

        if ($distanciaOriginal <= $limite) {
            return new ResultadoPonto(ConfirmacaoPonto::Confirmado, $lat, $lng, PropertyLocationSource::CadastroImobiliario, $distanciaOriginal, $distanciaOriginal);
        }

        foreach ($this->alternativos->candidatos($inscricao, $codLog, $numero, $enderecoParaGeocode) as $candidato) {
            $distancia = DistanciaGeografica::ateLinhas($candidato->lat, $candidato->lng, $eixo->linhas);

            if ($distancia !== null && $distancia <= $limite) {
                return new ResultadoPonto(ConfirmacaoPonto::Substituido, $candidato->lat, $candidato->lng, $candidato->fonte, $distanciaOriginal, $distancia);
            }
        }

        return new ResultadoPonto(ConfirmacaoPonto::NaoConfirmado, $lat, $lng, PropertyLocationSource::CadastroImobiliario, $distanciaOriginal);
    }
}
