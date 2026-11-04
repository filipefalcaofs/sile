<?php

namespace App\Services\Servicos;

use App\Models\ServicoNomenclatura;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;

/**
 * Alteração de endereço da JUCEB na ficha de análise: o serviço identificado
 * (Alteração de Endereço ou Revisão de Espaço) e os campos que mudaram em
 * relação ao último processo da empresa, com o valor de antes e o de agora.
 */
class MudancaEnderecoFicha
{
    /** @var array<string, string> */
    private const CAMPOS = [
        'cod_log' => 'CodLog',
        'address_street' => 'Logradouro',
        'address_number' => 'Nº',
        'address_neighborhood' => 'Bairro',
        'address_complement' => 'Complemento',
        'address_zip' => 'CEP',
    ];

    public function __construct(
        private CondicaoServicoDetector $detector,
        private ServicoNomenclaturaResolver $nomenclatura,
    ) {}

    /**
     * @return array{servico: string, processo_anterior: ?string, campos: list<array{campo: string, antes: string, depois: string}>}|null
     */
    public function dados(ViabilityRequest $processo): ?array
    {
        $processo->loadMissing('eventos');

        $evento = $processo->eventos->first(fn (ViabilityRequestEvento $evento): bool => str_starts_with(ServicoNomenclatura::chaveDe((string) $evento->nome), 'alteracao de endereco'));

        if ($evento === null) {
            return null;
        }

        $anterior = $this->detector->anteriorDaEmpresa($processo);

        return [
            'servico' => $this->nomenclatura->nomePara((string) $evento->nome, $evento->condicao),
            'processo_anterior' => $anterior?->protocol_number,
            'campos' => $anterior === null ? [] : $this->campos($processo, $anterior),
        ];
    }

    /**
     * @return list<array{campo: string, antes: string, depois: string}>
     */
    private function campos(ViabilityRequest $processo, ViabilityRequest $anterior): array
    {
        $campos = [];

        foreach (self::CAMPOS as $atributo => $rotulo) {
            $depois = trim((string) $processo->getAttribute($atributo));
            $antes = trim((string) $anterior->getAttribute($atributo));

            if (ServicoNomenclatura::chaveDe($depois) !== ServicoNomenclatura::chaveDe($antes)) {
                $campos[] = ['campo' => $rotulo, 'antes' => $antes, 'depois' => $depois];
            }
        }

        $areaDepois = $processo->used_area_m2;
        $areaAntes = $anterior->used_area_m2;

        if ($areaDepois !== null && $areaAntes !== null && round((float) $areaDepois, 2) !== round((float) $areaAntes, 2)) {
            $campos[] = ['campo' => 'Área utilizada (m²)', 'antes' => $this->area($areaAntes), 'depois' => $this->area($areaDepois)];
        }

        return $campos;
    }

    private function area(mixed $valor): string
    {
        return number_format((float) $valor, 2, ',', '.');
    }
}
