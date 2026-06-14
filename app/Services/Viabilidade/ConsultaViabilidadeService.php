<?php

namespace App\Services\Viabilidade;

use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\TerritoryResult;
use App\Services\Geo\TerritoryService;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use App\Support\Audit\AuditService;

/**
 * Orquestrador da consulta prévia de viabilidade (HU-054 a HU-059): COMPÕE os
 * motores reais (Geocoder → TerritoryService → LouosEnquadramentoService →
 * RiscoClassificationService) num ConsultaViabilidadeResult e AUDITA (RN-002) —
 * sem nenhuma lógica de decisão própria.
 *
 * REGRA ANTI-FACHADA: o serviço só PASSA ADIANTE as saídas dos motores. O
 * veredito locacional é PROPAGADO do consolidado do motor LOUOS (HU-044) — a
 * degradação honesta "sem zona → pendente" é verdade única do motor; o
 * orquestrador NUNCA recomputa nem inventa permitido/não permitido. Endereço não
 * localizado propaga a exceção do geocoder (o controller a traduz) e a inscrição
 * indisponível degrada para a via CNAE — JAMAIS inventa um ponto.
 *
 * Há três entradas honestas (HU-054/055/056): endereço (pipeline completo),
 * CNAE (risco + Quadro 7 por área, sem território) e inscrição imobiliária (via
 * contrato PropertyRegistryLookup — resolve quando a base oficial existir,
 * degrada com aviso enquanto pendente SEDUR).
 */
class ConsultaViabilidadeService
{
    private const AVISO_ZONA_PENDENTE = 'Veredito locacional pendente: zona urbanística pendente da base oficial (SEDUR).';

    private const AVISO_CNAE_SEM_LOCAL = 'Consulta por CNAE não avalia o local: o veredito locacional depende do endereço/zona. Para a viabilidade locacional, consulte por endereço.';

    public function __construct(
        private Geocoder $geocoder,
        private TerritoryService $territory,
        private LouosEnquadramentoService $louos,
        private RiscoClassificationService $risco,
        private PropertyRegistryLookup $propertyRegistry,
        private AuditService $audit,
    ) {}

    /**
     * Consulta por ENDEREÇO (HU-054): geocodifica (real), identifica o
     * território e orquestra os motores — pipeline de ponta a ponta.
     *
     * NÃO captura AddressNotFoundException/GeocoderException: deixa propagar para
     * o controller (07-06) traduzir em mensagem honesta — endereço não
     * localizado/serviço indisponível NUNCA vira resultado falso (sem fachada).
     */
    public function consultarPorEndereco(string $endereco, string $cnae, ?float $area = null): ConsultaViabilidadeResult
    {
        $geocode = $this->geocoder->geocode($endereco);

        $input = ConsultaViabilidadeInput::paraEndereco($endereco, $cnae, $area);

        return $this->consultarPorPonto($geocode->latitude, $geocode->longitude, $input, $geocode);
    }

    /**
     * Consulta por CNAE (HU-056): risco real e, quando há área, Quadro 7 — SEM
     * território (sem ponto, sem zona/via). O veredito locacional fica pendente
     * (o motor degrada sozinho) e a consulta avisa que não avalia o local.
     */
    public function consultarPorCnae(string $cnae, ?float $area = null): ConsultaViabilidadeResult
    {
        $input = ConsultaViabilidadeInput::paraCnae($cnae, $area);

        return $this->consultarPorCnaeComEntrada($input, [self::AVISO_CNAE_SEM_LOCAL]);
    }

    /**
     * Análise por CNAE + área SEM território, reutilizada pela via CNAE pura
     * (HU-056) e pela inscrição degradada (HU-055, quando a base de lotes está
     * indisponível): enquadra com território NULL (Quadro 10 indisponível →
     * consolidado pendente; Quadro 7 por área roda) e classifica o risco real.
     * Os avisos comunicam a degradação específica de cada caminho.
     *
     * @param  list<string>  $avisos
     */
    private function consultarPorCnaeComEntrada(ConsultaViabilidadeInput $input, array $avisos): ConsultaViabilidadeResult
    {
        $enquadramento = $this->louos->enquadrar(
            EnquadramentoInput::paraConsulta((float) ($input->area ?? 0.0), $input->cnae, null),
        );
        $risco = $this->risco->classify(RiscoInput::paraCnae($input->cnae));

        return $this->comporEResultar($input, null, null, $enquadramento, $risco, $avisos);
    }

    /**
     * Pipeline central a partir de um ponto resolvido (do geocoder ou da
     * inscrição): identifica o território, enquadra (LOUOS, COM o território) e
     * classifica o risco. Sem zona identificada, acrescenta o aviso honesto — o
     * veredito em si JÁ vem pendente do motor (apenas propagado).
     */
    private function consultarPorPonto(float $lat, float $lng, ConsultaViabilidadeInput $input, ?GeocodeResult $geocode): ConsultaViabilidadeResult
    {
        $territory = $this->territory->identify($lat, $lng);

        $avisos = [];

        if (($territory->zona['status'] ?? null) !== 'identificado') {
            $avisos[] = self::AVISO_ZONA_PENDENTE;
        }

        $enquadramento = $this->louos->enquadrar(
            EnquadramentoInput::paraConsulta((float) ($input->area ?? 0.0), $input->cnae, $territory),
        );
        $risco = $this->risco->classify(RiscoInput::paraCnae($input->cnae));

        return $this->comporEResultar($input, $geocode, $territory, $enquadramento, $risco, $avisos);
    }

    /**
     * Montagem do ConsultaViabilidadeResult + auditoria (RN-002), reutilizada por
     * TODAS as entradas (DRY). O serviço só compõe os sub-resultados reais dos
     * motores e propaga o veredito — não decide nada.
     *
     * @param  list<string>  $avisos
     */
    private function comporEResultar(
        ConsultaViabilidadeInput $input,
        ?GeocodeResult $geocode,
        ?TerritoryResult $territory,
        EnquadramentoResult $enquadramento,
        RiscoResult $risco,
        array $avisos,
    ): ConsultaViabilidadeResult {
        $cnaeNormalizado = $this->normalizarCnae($input->cnae);

        $result = new ConsultaViabilidadeResult(
            entrada: [
                'tipo' => $input->tipo,
                'cnae' => $cnaeNormalizado,
                'cnae_formatado' => $this->formatarCnae($cnaeNormalizado),
                'area' => $input->area,
                'endereco' => $input->endereco,
                'inscricao' => $input->inscricao,
            ],
            geocode: $geocode,
            territory: $territory,
            enquadramento: $enquadramento,
            risco: $risco,
            avisos: $avisos,
        );

        // Auditoria única por consulta (RN-002), espelhando TerritoryService:
        // causer null quando anônimo (resolvido no AuditService) + origem/IP
        // enriquecidos pela RecordActivityAction.
        $this->audit->log(
            logName: 'viabilidade',
            event: 'consulta',
            description: "Consulta de viabilidade ({$input->tipo}) do CNAE {$input->cnae}",
            properties: [
                'tipo' => $input->tipo,
                'cnae' => $cnaeNormalizado,
                'area' => $input->area,
                'veredito' => $result->vereditoLocacional()['resultado'],
                'encaminhamento_risco' => $risco->encaminhamento['fluxo'] ?? null,
                'avisos' => $result->avisos,
                'versoes' => $result->versoes(),
            ],
            result: 'sucesso',
        );

        return $result;
    }

    /**
     * CNAE em dígitos (precedente dos motores do import) — normaliza qualquer
     * máscara recebida para os 7 dígitos da subclasse.
     */
    private function normalizarCnae(string $cnae): string
    {
        return (string) preg_replace('/\D/', '', $cnae);
    }

    /**
     * Formata os 7 dígitos da subclasse para o padrão oficial 0000-0/00. Mantém
     * o valor recebido quando não tiver 7 dígitos (honesto — não inventa formato).
     */
    private function formatarCnae(string $digitos): string
    {
        if (strlen($digitos) !== 7) {
            return $digitos;
        }

        return substr($digitos, 0, 4).'-'.substr($digitos, 4, 1).'/'.substr($digitos, 5, 2);
    }
}
