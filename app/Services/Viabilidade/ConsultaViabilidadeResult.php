<?php

namespace App\Services\Viabilidade;

use App\Enums\ResultadoViabilidade;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;

/**
 * Resultado imutável da consulta prévia de viabilidade (HU-054 a HU-059),
 * espelhando RiscoResult/EnquadramentoResult/TerritoryResult. É o SNAPSHOT que o
 * serviço (07-05) preenche, o endpoint (07-06) serializa e o histórico (07-03/07)
 * persiste.
 *
 * O Result AGREGA os sub-resultados reais dos motores (geocode, território,
 * enquadramento LOUOS e risco) — não recria nem recomputa nenhum deles. As
 * "simulações" das HUs são VIEWS deste mesmo objeto: enquadramento (HU-057),
 * risco (HU-058) e restrições do território (HU-059).
 *
 * REGRA ANTI-FACHADA: o veredito locacional é PROPAGADO do consolidado do motor
 * LOUOS (HU-044) — a degradação "sem zona → pendente" é verdade única do motor;
 * o orquestrador NUNCA decide nem recomputa o veredito.
 */
final readonly class ConsultaViabilidadeResult
{
    /**
     * @param  array<string, mixed>  $entrada  Metadados da entrada (tipo, cnae, cnae_formatado, area, endereco?, inscricao?).
     * @param  list<string>  $avisos  Degradações honestas comunicadas (zona pendente, inscrição indisponível, consulta por CNAE sem local).
     */
    public function __construct(
        public array $entrada,
        public ?GeocodeResult $geocode,
        public ?TerritoryResult $territory,
        public EnquadramentoResult $enquadramento,
        public RiscoResult $risco,
        public array $avisos = [],
    ) {}

    /**
     * Veredito locacional PROPAGADO do consolidado do motor LOUOS — não decidido
     * aqui. A degradação "sem zona → pendente" já vem do motor (HU-044); o
     * orquestrador apenas espelha `resultado`/`label`/`motivo`.
     *
     * @return array<string, ?string>
     */
    public function vereditoLocacional(): array
    {
        $resultado = $this->enquadramento->resultado();

        return [
            'resultado' => $resultado,
            'label' => ResultadoViabilidade::from($resultado)->label(),
            'motivo' => $this->enquadramento->consolidado['motivo'] ?? null,
        ];
    }

    /**
     * União honesta das referências legais já produzidas pelos motores (LOUOS +
     * risco), sem duplicar — não inventa fundamentação nova.
     *
     * @return list<string>
     */
    public function fundamentacao(): array
    {
        return array_values(array_unique([
            ...($this->enquadramento->consolidado['fundamentacao'] ?? []),
            ...$this->risco->fundamentacao,
        ]));
    }

    /**
     * Versões de TODAS as regras aplicadas (RN-002/RN-004) — insumo da auditoria e
     * da reprodução por época. Território ausente (consulta por CNAE) degrada para
     * um mapa vazio, sem inventar versão.
     *
     * @return array<string, array<string, ?string>>
     */
    public function versoes(): array
    {
        return [
            'territorio' => $this->territory?->versoes() ?? [],
            'louos' => $this->enquadramento->versoes(),
            'risco' => $this->risco->versoes(),
        ];
    }

    /**
     * Contrato snake_case do endpoint JSON (07-06) e do snapshot do histórico
     * (07-03/07), com as seções enquadramento (HU-057), risco (HU-058) e
     * restrições (HU-059). Sub-resultados ausentes degradam para null sem inventar.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'entrada' => $this->entrada,
            'geocode' => $this->geocode?->toArray(),
            'territorio' => $this->territory?->toArray(),
            'enquadramento' => $this->enquadramento->toArray(),
            'risco' => $this->risco->toArray(),
            'restricoes' => $this->territory?->restricoes,
            'veredito_locacional' => $this->vereditoLocacional(),
            'fundamentacao' => $this->fundamentacao(),
            'avisos' => $this->avisos,
            'versoes' => $this->versoes(),
        ];
    }
}
