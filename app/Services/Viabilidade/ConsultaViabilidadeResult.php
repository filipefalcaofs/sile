<?php

namespace App\Services\Viabilidade;

use App\Enums\ResultadoViabilidade;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Risco\RiscoResult;

/**
 * Resultado imutável da consulta prévia de viabilidade (HU-052 a HU-057),
 * espelhando RiscoResult/EnquadramentoResult/TerritoryResult. É o SNAPSHOT que o
 * serviço (07-05) preenche, o endpoint (07-06) serializa e o histórico (07-03/07)
 * persiste.
 *
 * O Result AGREGA os sub-resultados reais dos motores (geocode, território,
 * enquadramento LOUOS e risco) — não recria nem recomputa nenhum deles. As
 * "simulações" das HUs são VIEWS deste mesmo objeto: enquadramento (HU-055),
 * risco (HU-056) e restrições do território (HU-057).
 *
 * REGRA ANTI-FACHADA: o veredito locacional é PROPAGADO do consolidado do motor
 * LOUOS (HU-042) — a degradação "sem zona → pendente" é verdade única do motor;
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
        private ?ConfiabilidadeConsultaResultado $confiabilidadeResolvida = null,
    ) {}

    /**
     * Cópia do resultado com a confiabilidade já resolvida (calculada uma única vez
     * pelo serviço e reutilizada no payload, na auditoria e no histórico).
     */
    public function comConfiabilidade(ConfiabilidadeConsultaResultado $confiabilidade): self
    {
        return new self(
            $this->entrada,
            $this->geocode,
            $this->territory,
            $this->enquadramento,
            $this->risco,
            $this->avisos,
            $confiabilidade,
        );
    }

    /**
     * Veredito locacional PROPAGADO do consolidado do motor LOUOS — não decidido
     * aqui. A degradação "sem zona → pendente" já vem do motor (HU-042); o
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
     * Confiabilidade do resultado (conclusiva, incompleta, inconclusiva), derivada
     * do que os motores devolveram — não decide veredito.
     */
    public function confiabilidade(): ConfiabilidadeConsultaResultado
    {
        return $this->confiabilidadeResolvida
            ?? (new ConfiabilidadeConsultaResolver(new DecisionTextCatalog))->resolver($this);
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
     * (07-03/07), com as seções enquadramento (HU-055), risco (HU-056) e
     * restrições (HU-057). Sub-resultados ausentes degradam para null sem inventar.
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
            'confiabilidade' => $this->confiabilidade()->toArray(),
            'fundamentacao' => $this->fundamentacao(),
            'avisos' => $this->avisos,
            'versoes' => $this->versoes(),
        ];
    }
}
