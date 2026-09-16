<?php

namespace App\Services\Risco;

use Carbon\CarbonInterface;

/**
 * Entrada imutável da classificação de risco (HU-047 a HU-051), espelhando o
 * padrão de DTO readonly de TerritoryResult. Reúne tudo que o motor (06-05)
 * precisa para decidir: o CNAE, as respostas às condicionantes-pergunta (mapa
 * condicionante_id/pergunta → resposta booleana), os gatilhos já ativos no
 * contexto (ex.: zeis_especial vindo do território da Fase 4) e, opcionalmente,
 * a data para reprodução por época (null = versão vigente).
 */
final readonly class RiscoInput
{
    /**
     * @param  array<int|string, bool>  $respostasCondicionantes  Respostas às condicionantes (id/pergunta → bool).
     * @param  list<string>  $gatilhosContexto  Valores de TipoGatilho ativos no contexto (ex.: 'zeis_especial').
     * @param  ?float  $areaUtilizada  Área onde a atividade será exercida (corte 1.250 m² da planilha 20.08.26).
     * @param  ?TipoImovel  $tipoImovel  Tipo enviado pelo REGIN; nulo = ausente.
     * @param  ?string  $subcategoriaUso  Subcategoria do enquadramento (nR1-12, ID3-01, …).
     */
    public function __construct(
        public string $cnaeCode,
        public array $respostasCondicionantes = [],
        public array $gatilhosContexto = [],
        public ?CarbonInterface $data = null,
        public ?float $areaUtilizada = null,
        public ?TipoImovel $tipoImovel = null,
        public ?string $subcategoriaUso = null,
    ) {}

    /**
     * Atalho para classificar um CNAE sem respostas de condicionante nem
     * gatilhos de contexto (consulta simples, versão vigente).
     */
    public static function paraCnae(string $cnae): self
    {
        return new self($cnae);
    }
}
