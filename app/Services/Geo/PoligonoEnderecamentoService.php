<?php

namespace App\Services\Geo;

use App\Enums\PoligonoFonte;
use App\Models\ViabilityRequest;
use App\Support\Audit\AuditService;
use App\Support\Settings;

/**
 * Grava no processo a geometria do lote, em cadeia de fontes oficiais:
 *
 * 1. Endereçamento SEDUR, pelo CODLOG do logradouro tributário, número e
 *    complemento — só cobre endereço licenciado;
 * 2. Cadastro municipal (SEMAP), pela inscrição imobiliária — cobre o que o
 *    endereçamento não tem, inclusive a chave ambígua por bloco;
 * 3. Endereçamento SEDUR pelo ponto do imóvel — consulta espacial lenta
 *    (~30 s), só quando o chamador pede (`consultarPorPonto`), em fila.
 *
 * Nenhuma das três com polígono único não grava nada, e cada tentativa fica na
 * auditoria com a fonte. Desenho do requerente não entra aqui.
 */
class PoligonoEnderecamentoService
{
    public function __construct(
        private GeoServerEnderecamentoClient $cliente,
        private SemapLoteClient $semap,
        private AuditService $audit,
    ) {}

    public function aplicar(ViabilityRequest $request, bool $consultarPorPonto = false): bool
    {
        $codLog = trim((string) $request->cod_log);
        $numero = trim((string) $request->address_number);
        $complemento = $request->address_complement;

        if ($codLog === '' || $numero === '') {
            $this->auditar($request, 'enderecamento', 'sem_chave', $codLog, $numero, $complemento, new EnderecamentoHit('sem_chave', motivo: 'Processo sem CODLOG ou número métrico.'));
        } else {
            $hit = $this->cliente->consultar($codLog, $numero, $complemento);

            $this->auditar($request, 'enderecamento', $hit->status, $codLog, $numero, $complemento, $hit);

            if ($hit->encontrado()) {
                $this->gravar($request, $hit, PoligonoFonte::Chave);

                return true;
            }
        }

        $semap = $this->semap->consultar($request->property_registration);

        $this->auditar($request, 'semap', $semap->status, $codLog, $numero, $complemento, $semap);

        if (! $semap->encontrado()) {
            return $consultarPorPonto && $this->aplicarPorPonto($request);
        }

        $this->gravar($request, $semap, PoligonoFonte::Semap, atributosDoLote: true);

        return true;
    }

    private function gravar(ViabilityRequest $request, EnderecamentoHit $hit, PoligonoFonte $fonte, bool $atributosDoLote = false): void
    {
        $dados = [
            'address_polygon_geojson' => $hit->geometria,
            'address_polygon_consulted_at' => now(),
            'address_polygon_source' => $fonte,
        ];

        if ($atributosDoLote) {
            $propriedades = $hit->propriedades ?? [];
            $dados['semap_use_category'] = $this->texto($propriedades['CAT_DE_USO'] ?? null);
            $dados['semap_construction_standard'] = $this->texto($propriedades['PADRAOCONS'] ?? null);
        }

        $request->forceFill($dados)->save();
    }

    /**
     * Terceira fonte: o lote que contém o ponto do imóvel. Não consulta quando
     * o processo já tem polígono ou não tem ponto, então repetir a chamada (nova
     * tentativa do job) não repete a consulta lenta.
     */
    public function aplicarPorPonto(ViabilityRequest $request): bool
    {
        $ponto = $request->pontoDoImovel();

        if ($ponto === null || ! $this->precisaConsultarPorPonto($request)) {
            return false;
        }

        $hit = $this->cliente->consultarPorPonto($ponto['lat'], $ponto['lng']);

        $this->auditarPonto($request, $ponto, $hit);

        if (! $hit->encontrado()) {
            return false;
        }

        $this->gravar($request, $hit, PoligonoFonte::Ponto);

        return true;
    }

    /**
     * A consulta por ponto faz sentido: falta o polígono, há ponto e o
     * endereçamento está ligado. O reprocesso usa a mesma regra para decidir
     * se agenda a decisão em fila.
     */
    public function precisaConsultarPorPonto(ViabilityRequest $request): bool
    {
        $semPoligono = ! is_array($request->address_polygon_geojson) || $request->address_polygon_geojson === [];

        return $semPoligono
            && $request->pontoDoImovel() !== null
            && Settings::enabled('geoserver_enderecamento');
    }

    private function texto(mixed $valor): ?string
    {
        if (! is_string($valor) && ! is_numeric($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto === '' ? null : $texto;
    }

    private function auditar(ViabilityRequest $request, string $fonte, string $status, string $codLog, string $numero, ?string $complemento, EnderecamentoHit $hit): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'enderecamento-poligono',
            description: "Polígono do lote em {$fonte} ({$status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'fonte' => $fonte,
                'cod_log' => $codLog,
                'numero' => $numero,
                'complemento' => $complemento,
                'inscricao' => $fonte === 'semap' ? $request->property_registration : null,
                'status' => $status,
                'candidatas' => $hit->candidatas,
                'motivo' => $hit->motivo,
            ],
            subject: $request,
            result: $status,
        );
    }

    /**
     * @param  array{lat: float, lng: float}  $ponto
     */
    private function auditarPonto(ViabilityRequest $request, array $ponto, EnderecamentoHit $hit): void
    {
        $this->audit->log(
            logName: 'geo',
            event: 'enderecamento-poligono',
            description: "Polígono do lote em ponto ({$hit->status}) na solicitação #{$request->id}",
            properties: [
                'viability_request_id' => $request->id,
                'fonte' => 'ponto',
                'lat' => $ponto['lat'],
                'lng' => $ponto['lng'],
                'status' => $hit->status,
                'candidatas' => $hit->candidatas,
                'motivo' => $hit->motivo,
            ],
            subject: $request,
            result: $hit->status,
        );
    }
}
