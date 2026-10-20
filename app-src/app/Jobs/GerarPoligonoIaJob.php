<?php

namespace App\Jobs;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Enums\ViabilityRequestStatus;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Services\Expresso\FluxoExpressoService;
use App\Services\Geo\Edificacoes\AceiteMascaraIa;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\EdificacaoCandidata;
use App\Services\Geo\Edificacoes\InferenciaEdificacaoClient;
use App\Services\Geo\Edificacoes\InferenciaIndisponivelException;
use App\Services\Geo\Edificacoes\OrtofotoClient;
use App\Services\Geo\Edificacoes\PoligonoComplementarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

/**
 * Último recurso do polígono do lote: recorta a ortofoto da Prefeitura em
 * volta do ponto, pede o contorno ao serviço de inferência e grava só se o
 * contorno passar no aceite. Um ponto, uma inferência por vez. Ao fim, a
 * decisão do processo volta para a fila.
 */
class GerarPoligonoIaJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout;

    public function __construct(public int $viabilityRequestId)
    {
        $this->timeout = (int) config('viabiliza.edificacoes.ia.timeout', 120) + 60;
        $fila = (string) config('viabiliza.edificacoes.ia.fila', 'default');

        if ($fila !== '' && $fila !== 'default') {
            $this->onQueue($fila);
        }
    }

    public function uniqueId(): string
    {
        $ponto = ViabilityRequest::query()->find($this->viabilityRequestId)?->pontoDoImovel();

        return $ponto === null
            ? "ia:processo:{$this->viabilityRequestId}"
            : 'ia:'.sha1(round($ponto['lat'], 6).','.round($ponto['lng'], 6));
    }

    public function handle(
        OrtofotoClient $ortofoto,
        InferenciaEdificacaoClient $inferencia,
        AceiteMascaraIa $aceite,
        BuildingFootprintRepository $base,
        PoligonoComplementarService $complementar,
    ): void {
        $request = ViabilityRequest::query()->find($this->viabilityRequestId);
        $ponto = $request?->pontoDoImovel();

        if ($request === null || $ponto === null) {
            return;
        }

        try {
            $this->gerar($request, $ponto['lat'], $ponto['lng'], $ortofoto, $inferencia, $aceite, $base, $complementar);
        } finally {
            $atual = $request->fresh();

            if ($atual !== null && $this->aguardandoIa($atual)) {
                DecidirFluxoExpressoJob::dispatch($atual->id, reavaliacao: $atual->status !== ViabilityRequestStatus::Protocolada);
            }
        }
    }

    /**
     * O processo ainda espera a IA: pode voltar ao motor e a decisão não seguiu
     * sem ela. Processo já decidido não recebe polígono que a decisão não usou.
     */
    private function aguardandoIa(ViabilityRequest $request): bool
    {
        $prazoEsgotado = LotPolygonResolution::query()
            ->where('viability_request_id', $request->id)
            ->where('source', PoligonoFonte::Ia)
            ->where('reason', 'prazo_esgotado')
            ->exists();

        return ! $prazoEsgotado && app(FluxoExpressoService::class)->podeReavaliar($request);
    }

    private function gerar(
        ViabilityRequest $request,
        float $lat,
        float $lng,
        OrtofotoClient $ortofoto,
        InferenciaEdificacaoClient $inferencia,
        AceiteMascaraIa $aceite,
        BuildingFootprintRepository $base,
        PoligonoComplementarService $complementar,
    ): void {
        if (is_array($request->address_polygon_geojson) && $request->address_polygon_geojson !== []) {
            return;
        }

        $existente = $base->contendoPonto(FonteEdificacao::Ia, $lat, $lng);

        if ($existente !== null) {
            if ($this->aguardandoIa($request)) {
                $complementar->gravar($request, $existente);
            }

            return;
        }

        $inicio = hrtime(true);
        $recorte = $ortofoto->recortar($lat, $lng);

        if ($recorte === null) {
            $this->falha($complementar, $request, StatusResolucaoPoligono::NaoResolvido, 'imagem_indisponivel', $inicio);

            return;
        }

        try {
            $mascara = $inferencia->segmentar($recorte, ...$recorte->pixel($lat, $lng));
        } catch (InferenciaIndisponivelException) {
            $this->falha($complementar, $request, StatusResolucaoPoligono::Indisponivel, 'servico_fora', $inicio);

            return;
        }

        $avaliacao = $aceite->avaliar($mascara, $recorte, $lat, $lng);
        $normal = $avaliacao['aceita'] ? $base->normalizar($avaliacao['geojson']) : null;

        if ($normal === null || ! $normal['valida']) {
            $this->falha($complementar, $request, StatusResolucaoPoligono::NaoResolvido, $avaliacao['motivo'] ?? 'geometria_invalida', $inicio);

            return;
        }

        $release = "{$mascara->modelo}+{$recorte->servico}-{$recorte->ano}";
        $sourceId = (string) Str::uuid();
        $gravado = $base->upsert(
            fonte: FonteEdificacao::Ia,
            sourceId: $sourceId,
            release: $release,
            geojson: $normal['geojson'],
            areaM2: (float) $normal['area_m2'],
            confidence: null,
            sourceDatasets: null,
            properties: ['score_modelo' => $mascara->score, 'ortofoto' => "{$recorte->servico}-{$recorte->ano}"],
            importId: null,
            duplicateOfId: null,
            notas: $normal['notas'],
        );

        if (! $this->aguardandoIa($request->refresh())) {
            return;
        }

        $complementar->gravar($request, new EdificacaoCandidata(
            id: $gravado['id'],
            fonte: FonteEdificacao::Ia,
            sourceId: $sourceId,
            release: $release,
            geojson: $normal['geojson'],
            areaM2: (float) $normal['area_m2'],
            confidence: null,
            metodo: 'ia_ponto',
        ), (int) round((hrtime(true) - $inicio) / 1_000_000));
    }

    private function falha(PoligonoComplementarService $complementar, ViabilityRequest $request, StatusResolucaoPoligono $status, string $motivo, int $inicio): void
    {
        $complementar->registrar($request, PoligonoFonte::Ia, $status, [
            'reason' => $motivo,
            'latency_ms' => (int) round((hrtime(true) - $inicio) / 1_000_000),
        ]);
    }
}
