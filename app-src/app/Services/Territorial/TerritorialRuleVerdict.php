<?php

namespace App\Services\Territorial;

use App\Models\TerritorialRule;

/**
 * Veredito imutável da avaliação das regras territoriais sobre a solicitação
 * (RT-01 a RT-05). `sem_incidencia` = nenhuma regra ativa casou — o fluxo
 * normal segue. `indeferir` (RT-01) e `encaminhar_analise` (RT-02/03/04 e a
 * degradação CA-RT-09) carregam a regra, o motivo (texto legal renderizado ou
 * motivo de análise exato do requisito) e a versão do dado (RN-002).
 */
final readonly class TerritorialRuleVerdict
{
    public const SEM_INCIDENCIA = 'sem_incidencia';

    public const INDEFERIR = 'indeferir';

    public const ENCAMINHAR_ANALISE = 'encaminhar_analise';

    /**
     * @param  list<array<string, mixed>>  $incidencias  Trilha das regras avaliadas/incidentes (auditoria).
     */
    public function __construct(
        public string $status,
        public ?TerritorialRule $regra = null,
        public ?string $motivo = null,
        public ?string $versaoRegra = null,
        public array $incidencias = [],
    ) {}

    public static function semIncidencia(?string $versaoRegra = null): self
    {
        return new self(self::SEM_INCIDENCIA, versaoRegra: $versaoRegra);
    }
}
