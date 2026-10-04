<?php

namespace App\Services\Regin;

/**
 * Declarações que o requerente presta no REGIN e que o motor NÃO consome:
 * ficam registradas no processo e visíveis ao analista. Separadas do
 * {@see RecebimentoProcesso} para não misturar o que decide com o que informa.
 */
final readonly class RecebimentoComplemento
{
    /**
     * @param  array{latitude: float, longitude: float}|null  $coordenada
     * @param  array{nome: ?string, documento: ?string, crc: ?string}|null  $contador
     * @param  list<array{cnae: string, exercida: string}>  $atividadesExercidasNoLocal
     */
    public function __construct(
        public ?string $evento,
        public ?string $objetoSocial,
        public ?string $naturezaJuridica,
        public ?float $areaConstruida,
        public ?float $areaTerreno,
        public ?array $coordenada,
        public ?bool $imovelEmCondominio,
        public ?bool $areaEmbarqueDesembarque,
        public ?bool $possuiConcessaoDeUso,
        public ?bool $termoDispensaViabilidadeLocacional,
        public ?string $bapAnteriorIndeferido,
        public ?bool $meiDesenquadrado,
        public ?array $contador,
        public array $atividadesExercidasNoLocal,
        public ?string $cnpjDestino,
        public ?string $cnpjEmpresa,
        public ?string $cnpjOrigem,
        public ?int $codFuncao,
        public ?string $dataGeracao,
        public ?string $nire,
        public ?string $raeCalifActv,
        public ?string $rgpTgeCodTipTab,
        public ?string $rgpValor,
    ) {}

    /**
     * @param  array<string, mixed>  $envelope
     */
    public static function fromEnvelope(array $envelope): self
    {
        return new self(
            evento: RecebimentoProcesso::texto($envelope['evento'] ?? null, 255),
            objetoSocial: RecebimentoProcesso::texto($envelope['objetoSocial'] ?? null, 4000),
            naturezaJuridica: RecebimentoProcesso::texto($envelope['naturezaJuridica'] ?? null, 255),
            areaConstruida: RecebimentoProcesso::area($envelope['areaEstabelecimento'] ?? $envelope['areaConstruida'] ?? null),
            areaTerreno: RecebimentoProcesso::area($envelope['areaTerreno'] ?? null),
            coordenada: self::coordenada($envelope['coordenadaGeografica'] ?? null),
            imovelEmCondominio: RecebimentoProcesso::booleano($envelope['imovelEmCondominio'] ?? null),
            areaEmbarqueDesembarque: RecebimentoProcesso::booleano($envelope['areaEmbarqueDesembarque'] ?? null),
            possuiConcessaoDeUso: RecebimentoProcesso::booleano($envelope['possuiConcessaoDeUso'] ?? null),
            termoDispensaViabilidadeLocacional: RecebimentoProcesso::booleano($envelope['termoDispensaViabilidadeLocacional'] ?? null),
            bapAnteriorIndeferido: RecebimentoProcesso::texto($envelope['bapAnteriorIndeferido'] ?? null, 60),
            meiDesenquadrado: RecebimentoProcesso::booleano($envelope['meiDesenquadrado'] ?? null),
            contador: self::contador($envelope['contador'] ?? null),
            atividadesExercidasNoLocal: self::atividadesNoLocal($envelope['atividadesExercidasNoLocal'] ?? null),
            cnpjDestino: RecebimentoProcesso::texto($envelope['cnpjDestino'] ?? null, 18),
            cnpjEmpresa: RecebimentoProcesso::texto($envelope['cnpjEmpresa'] ?? null, 18),
            cnpjOrigem: RecebimentoProcesso::texto($envelope['cnpjOrigem'] ?? null, 18),
            codFuncao: self::inteiro($envelope['codFuncao'] ?? null),
            dataGeracao: RecebimentoProcesso::texto($envelope['dataGeracao'] ?? null, 40),
            nire: RecebimentoProcesso::texto($envelope['nire'] ?? null, 40),
            raeCalifActv: RecebimentoProcesso::texto($envelope['RAE_CALIF_ACTV'] ?? null, 10),
            rgpTgeCodTipTab: RecebimentoProcesso::texto($envelope['RGP_TGE_COD_TIP_TAB'] ?? null, 10),
            rgpValor: RecebimentoProcesso::texto($envelope['RGP_VALOR'] ?? null, 60),
        );
    }

    /**
     * @param  array<string, mixed>  $dados
     */
    public static function fromStored(array $dados): self
    {
        return new self(
            evento: RecebimentoProcesso::texto($dados['evento'] ?? null, 255),
            objetoSocial: RecebimentoProcesso::texto($dados['objeto_social'] ?? null, 4000),
            naturezaJuridica: RecebimentoProcesso::texto($dados['natureza_juridica'] ?? null, 255),
            areaConstruida: RecebimentoProcesso::area($dados['area_construida'] ?? null),
            areaTerreno: RecebimentoProcesso::area($dados['area_terreno'] ?? null),
            coordenada: self::coordenada($dados['coordenada_geografica'] ?? null),
            imovelEmCondominio: RecebimentoProcesso::booleano($dados['imovel_em_condominio'] ?? null),
            areaEmbarqueDesembarque: RecebimentoProcesso::booleano($dados['area_embarque_desembarque'] ?? null),
            possuiConcessaoDeUso: RecebimentoProcesso::booleano($dados['possui_concessao_de_uso'] ?? null),
            termoDispensaViabilidadeLocacional: RecebimentoProcesso::booleano($dados['termo_dispensa_viabilidade_locacional'] ?? null),
            bapAnteriorIndeferido: RecebimentoProcesso::texto($dados['bap_anterior_indeferido'] ?? null, 60),
            meiDesenquadrado: RecebimentoProcesso::booleano($dados['mei_desenquadrado'] ?? null),
            contador: self::contador($dados['contador'] ?? null),
            atividadesExercidasNoLocal: self::atividadesNoLocal($dados['atividades_exercidas_no_local'] ?? null),
            cnpjDestino: RecebimentoProcesso::texto($dados['cnpj_destino'] ?? null, 18),
            cnpjEmpresa: RecebimentoProcesso::texto($dados['cnpj_empresa'] ?? null, 18),
            cnpjOrigem: RecebimentoProcesso::texto($dados['cnpj_origem'] ?? null, 18),
            codFuncao: self::inteiro($dados['cod_funcao'] ?? null),
            dataGeracao: RecebimentoProcesso::texto($dados['data_geracao'] ?? null, 40),
            nire: RecebimentoProcesso::texto($dados['nire'] ?? null, 40),
            raeCalifActv: RecebimentoProcesso::texto($dados['RAE_CALIF_ACTV'] ?? null, 10),
            rgpTgeCodTipTab: RecebimentoProcesso::texto($dados['RGP_TGE_COD_TIP_TAB'] ?? null, 10),
            rgpValor: RecebimentoProcesso::texto($dados['RGP_VALOR'] ?? null, 60),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'evento' => $this->evento,
            'objeto_social' => $this->objetoSocial,
            'natureza_juridica' => $this->naturezaJuridica,
            'area_construida' => $this->areaConstruida,
            'area_terreno' => $this->areaTerreno,
            'coordenada_geografica' => $this->coordenada,
            'imovel_em_condominio' => RecebimentoProcesso::simNao($this->imovelEmCondominio),
            'area_embarque_desembarque' => RecebimentoProcesso::simNao($this->areaEmbarqueDesembarque),
            'possui_concessao_de_uso' => RecebimentoProcesso::simNao($this->possuiConcessaoDeUso),
            'termo_dispensa_viabilidade_locacional' => RecebimentoProcesso::simNao($this->termoDispensaViabilidadeLocacional),
            'bap_anterior_indeferido' => $this->bapAnteriorIndeferido,
            'mei_desenquadrado' => RecebimentoProcesso::simNao($this->meiDesenquadrado),
            'contador' => $this->contador,
            'atividades_exercidas_no_local' => $this->atividadesExercidasNoLocal,
            'cnpj_destino' => $this->cnpjDestino,
            'cnpj_empresa' => $this->cnpjEmpresa,
            'cnpj_origem' => $this->cnpjOrigem,
            'cod_funcao' => $this->codFuncao,
            'data_geracao' => $this->dataGeracao,
            'nire' => $this->nire,
            'RAE_CALIF_ACTV' => $this->raeCalifActv,
            'RGP_TGE_COD_TIP_TAB' => $this->rgpTgeCodTipTab,
            'RGP_VALOR' => $this->rgpValor,
        ];
    }

    /**
     * @return array{latitude: float, longitude: float}|null
     */
    private static function coordenada(mixed $valor): ?array
    {
        if (! is_array($valor)) {
            return null;
        }

        $lat = $valor['latitude'] ?? $valor['lat'] ?? null;
        $lng = $valor['longitude'] ?? $valor['lng'] ?? null;

        if (! is_numeric($lat) || ! is_numeric($lng)) {
            return null;
        }

        return ['latitude' => (float) $lat, 'longitude' => (float) $lng];
    }

    /**
     * @return array{nome: ?string, documento: ?string, crc: ?string}|null
     */
    private static function contador(mixed $valor): ?array
    {
        if (! is_array($valor)) {
            return null;
        }

        $contador = [
            'nome' => RecebimentoProcesso::texto($valor['nome'] ?? null, 255),
            'documento' => preg_replace('/\D/', '', (string) ($valor['documento'] ?? '')) ?: null,
            'crc' => RecebimentoProcesso::texto($valor['crc'] ?? null, 40),
        ];

        return array_filter($contador) === [] ? null : $contador;
    }

    /**
     * @return list<array{cnae: string, exercida: string}>
     */
    private static function atividadesNoLocal(mixed $valor): array
    {
        if (! is_array($valor)) {
            return [];
        }

        $lista = [];

        foreach ($valor as $item) {
            if (! is_array($item)) {
                continue;
            }

            $cnae = RecebimentoProcesso::cnae($item['cnae'] ?? null);
            $exercida = RecebimentoProcesso::simNao(RecebimentoProcesso::booleano($item['exercida'] ?? null));

            if ($cnae === null || $exercida === null) {
                continue;
            }

            $lista[] = ['cnae' => $cnae, 'exercida' => $exercida];
        }

        return $lista;
    }

    private static function inteiro(mixed $valor): ?int
    {
        if (is_int($valor)) {
            return $valor;
        }

        if (is_string($valor) && preg_match('/^-?\d+$/', trim($valor)) === 1) {
            return (int) trim($valor);
        }

        return null;
    }
}
