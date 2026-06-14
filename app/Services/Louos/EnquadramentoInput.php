<?php

namespace App\Services\Louos;

use App\Services\Geo\TerritoryResult;
use Carbon\CarbonInterface;

/**
 * Entrada imutável do motor de enquadramento da LOUOS (HU-038 a HU-045),
 * espelhando RiscoInput: reúne tudo que o motor precisa para aplicar os Quadros
 * 7/10/11/11A — a área pretendida, o CNAE principal e os secundários, o
 * território (de onde vêm zona e via — Fase 4, hoje indisponível/pendente
 * SEDUR) e as vagas declaradas pelo requerente (HU-042).
 *
 * `data` reproduz a decisão por época (null = versão vigente). `versoesOverride`
 * é o modo sandbox (HU-143): resolve um domínio por uma versão específica
 * (rascunho) sem afetar a vigente — chave = value de RuleDomain, valor = número
 * da versão.
 */
final readonly class EnquadramentoInput
{
    /**
     * @param  list<string>  $cnaesSecundarios  CNAEs secundários (dígitos).
     * @param  array<string, mixed>  $vagasDeclaradas  Vagas/carga/descarga declaradas pelo requerente (HU-042).
     * @param  array<string, string>  $versoesOverride  Mapa domínio (RuleDomain->value) → versão específica (sandbox HU-143).
     */
    public function __construct(
        public float $area,
        public string $cnaePrincipal,
        public array $cnaesSecundarios = [],
        public ?TerritoryResult $territory = null,
        public array $vagasDeclaradas = [],
        public ?CarbonInterface $data = null,
        public array $versoesOverride = [],
    ) {}

    /**
     * Atalho para a consulta simples de viabilidade: área + CNAE (+ território
     * opcional), sem secundários, vagas, época ou override de versão.
     */
    public static function paraConsulta(float $area, string $cnae, ?TerritoryResult $territory = null): self
    {
        return new self(
            area: $area,
            cnaePrincipal: $cnae,
            territory: $territory,
        );
    }
}
