<?php

namespace App\Services\Viabilidade;

use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Geo\Geocoder;
use App\Services\Geo\GeocodeResult;
use App\Services\Geo\TerritoryResult;
use App\Services\Geo\TerritoryService;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Realty\PropertyNotFoundException;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryUnavailableException;
use App\Services\Risco\RiscoClassificationService;
use App\Services\Risco\RiscoInput;
use App\Services\Risco\RiscoResult;
use App\Services\Risco\TipoImovel;
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
 * CNAE (risco + enquadramento da planilha por área, sem território) e inscrição imobiliária (via
 * contrato PropertyRegistryLookup — resolve quando a base oficial existir,
 * degrada com aviso enquanto pendente SEDUR).
 */
class ConsultaViabilidadeService
{
    public function __construct(
        private Geocoder $geocoder,
        private TerritoryService $territory,
        private LouosEnquadramentoService $louos,
        private RiscoClassificationService $risco,
        private PropertyRegistryLookup $propertyRegistry,
        private AuditService $audit,
        private DecisionTextCatalog $textos,
    ) {}

    /**
     * Consulta por ENDEREÇO (HU-054): geocodifica (real), identifica o
     * território e orquestra os motores — pipeline de ponta a ponta.
     *
     * NÃO captura AddressNotFoundException/GeocoderException: deixa propagar para
     * o controller (07-06) traduzir em mensagem honesta — endereço não
     * localizado/serviço indisponível NUNCA vira resultado falso (sem fachada).
     */
    /**
     * @param  array<int, bool>  $respostas
     */
    public function consultarPorEndereco(string $endereco, string $cnae, ?float $area = null, array $respostas = []): ConsultaViabilidadeResult
    {
        $geocode = $this->geocoder->geocode($endereco);

        $input = ConsultaViabilidadeInput::paraEndereco($endereco, $cnae, $area, $respostas);

        return $this->consultarPorPonto($geocode->latitude, $geocode->longitude, $input, $geocode);
    }

    /**
     * Consulta por CNAE (HU-056): risco real e, quando há área, enquadramento
     * da planilha — SEM território (sem ponto, sem zona/via). O veredito locacional fica pendente
     * (o motor degrada sozinho) e a consulta avisa que não avalia o local.
     */
    /**
     * @param  array<int, bool>  $respostas
     */
    public function consultarPorCnae(string $cnae, ?float $area = null, ?TipoImovel $tipoImovel = null, array $respostas = []): ConsultaViabilidadeResult
    {
        $input = ConsultaViabilidadeInput::paraCnae($cnae, $area, $tipoImovel, $respostas);

        return $this->consultarPorCnaeComEntrada($input, [$this->textos->get('consulta.aviso.cnae_sem_local')]);
    }

    /**
     * Consulta por INSCRIÇÃO imobiliária (HU-055) via contrato PropertyRegistryLookup:
     * com a base de lotes disponível, resolve o ponto e roda a pipeline completa
     * (igual ao endereço, mas sem geocode); indisponível (pendente SEDUR), degrada
     * para a análise por CNAE + área com aviso — JAMAIS inventa um ponto.
     */
    /**
     * @param  array<int, bool>  $respostas
     */
    public function consultarPorInscricao(string $inscricao, string $cnae, ?float $area = null, array $respostas = []): ConsultaViabilidadeResult
    {
        $input = ConsultaViabilidadeInput::paraInscricao($inscricao, $cnae, $area, $respostas);

        try {
            $ponto = $this->propertyRegistry->resolve($inscricao);
        } catch (PropertyRegistryUnavailableException) {
            return $this->consultarPorCnaeComEntrada($input, [$this->textos->get('consulta.aviso.inscricao_indisponivel')]);
        } catch (PropertyNotFoundException) {
            return $this->consultarPorCnaeComEntrada($input, [
                'Inscrição imobiliária não encontrada no Cadastro (SEFAZ/SEDUR). Resultado sem análise territorial; consulte por endereço para o veredito locacional.',
            ]);
        }

        if ($ponto->latitude === null || $ponto->longitude === null) {
            return $this->consultarPorCnaeComEntrada($input, [
                'Inscrição localizada no Cadastro sem coordenada do lote. Resultado sem análise territorial; consulte por endereço para o veredito locacional.',
            ]);
        }

        return $this->consultarPorPonto($ponto->latitude, $ponto->longitude, $input, null);
    }

    /**
     * Consulta a partir de um PONTO já conhecido + CNAE (HU-141): ponto de
     * entrada PÚBLICO reutilizado pela simulação pré-protocolo (08-09), que já
     * tem o ponto (centroide do polígono da solicitação) e NÃO deve geocodificar
     * de novo. Roda a MESMA pipeline central (território → motores) e PROPAGA o
     * veredito do motor LOUOS — sem lógica de decisão paralela (RN-001). Não
     * altera o comportamento das três entradas públicas (endereço/CNAE/inscrição).
     */
    /**
     * @param  array<int, bool>  $respostas
     */
    public function consultarPorPontoConhecido(float $lat, float $lng, string $cnae, ?float $area = null, ?TipoImovel $tipoImovel = null, array $respostas = []): ConsultaViabilidadeResult
    {
        $input = ConsultaViabilidadeInput::paraPonto($cnae, $area, $tipoImovel, $respostas);

        return $this->consultarPorPonto($lat, $lng, $input, null);
    }

    /**
     * Consulta com território já resolvido (simulação REGIN: zona/via do
     * catálogo ou do formulário). Mesma pipeline de motores, sem GIS.
     *
     * @param  array<int, bool>  $respostas
     */
    public function consultarComTerritorio(TerritoryResult $territory, string $cnae, ?float $area = null, ?TipoImovel $tipoImovel = null, array $respostas = []): ConsultaViabilidadeResult
    {
        $input = ConsultaViabilidadeInput::paraPonto($cnae, $area, $tipoImovel, $respostas);

        $enquadramento = $this->louos->enquadrar(
            new EnquadramentoInput(
                area: (float) ($input->area ?? 0.0),
                cnaePrincipal: $input->cnae,
                territory: $territory,
                respostas: $input->respostas,
                tipoImovel: $input->tipoImovel,
            ),
        );

        return $this->comporEResultar($input, null, $territory, $enquadramento, $this->risco->classify($this->riscoInput($input)), []);
    }

    /**
     * Análise por CNAE + área SEM território, reutilizada pela via CNAE pura
     * (HU-056) e pela inscrição degradada (HU-055, quando a base de lotes está
     * indisponível): enquadra com território NULL (Quadro 10 indisponível →
     * consolidado pendente; o enquadramento da planilha por área roda) e classifica o risco real.
     * Os avisos comunicam a degradação específica de cada caminho.
     *
     * @param  list<string>  $avisos
     */
    private function consultarPorCnaeComEntrada(ConsultaViabilidadeInput $input, array $avisos): ConsultaViabilidadeResult
    {
        $enquadramento = $this->louos->enquadrar(
            new EnquadramentoInput(
                area: (float) ($input->area ?? 0.0),
                cnaePrincipal: $input->cnae,
                respostas: $input->respostas,
                tipoImovel: $input->tipoImovel,
            ),
        );
        $risco = $this->risco->classify($this->riscoInput($input));

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
            $avisos[] = $this->textos->get('consulta.aviso.zona_pendente');
        }

        $enquadramento = $this->louos->enquadrar(
            new EnquadramentoInput(
                area: (float) ($input->area ?? 0.0),
                cnaePrincipal: $input->cnae,
                territory: $territory,
                respostas: $input->respostas,
                tipoImovel: $input->tipoImovel,
            ),
        );
        $risco = $this->risco->classify($this->riscoInput($input));

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
    private function riscoInput(ConsultaViabilidadeInput $input): RiscoInput
    {
        return new RiscoInput(
            cnaeCode: $input->cnae,
            areaUtilizada: $input->area,
            tipoImovel: $input->tipoImovel,
            respostasTratamento: $input->respostas,
        );
    }

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
