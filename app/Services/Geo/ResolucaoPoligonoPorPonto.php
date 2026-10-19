<?php

namespace App\Services\Geo;

use App\Enums\FonteEdificacao;
use App\Enums\PoligonoFonte;
use App\Enums\StatusResolucaoPoligono;
use App\Jobs\GerarPoligonoIaJob;
use App\Models\LotPolygonResolution;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\PoligonoComplementarService;
use App\Support\Settings;

/**
 * Polígono do lote pelo ponto do imóvel, depois que a chave e o SEMAP não
 * acharam: edificação da Overture, da Microsoft, gerada por IA e, por
 * último, o endereçamento SEDUR pelo ponto. A IA roda em job próprio; quem
 * chama espera por ela até o prazo e depois segue sem ela.
 */
class ResolucaoPoligonoPorPonto
{
    public function __construct(
        private PoligonoComplementarService $complementar,
        private PoligonoEnderecamentoService $enderecamento,
    ) {}

    /**
     * Devolve verdadeiro quando a decisão deve esperar a inferência da IA.
     */
    public function resolver(ViabilityRequest $request, bool $podeEsperarIa): bool
    {
        if ($this->temPoligono($request)) {
            return false;
        }

        $aguardarIa = $podeEsperarIa && $this->iaAindaPodeResponder($request);
        $resultado = $this->complementar->resolver($request, $aguardarIa);

        if ($resultado->precisaIa) {
            GerarPoligonoIaJob::dispatch($request->id);

            return true;
        }

        if ($resultado->status === StatusResolucaoPoligono::Encontrado) {
            return false;
        }

        $this->registrarPrazoEsgotado($request, $aguardarIa);
        $this->enderecamento->aplicarPorPonto($request->refresh());

        return false;
    }

    /**
     * Falta polígono, há ponto e alguma fonte pelo ponto está ligada.
     */
    public function precisaResolver(ViabilityRequest $request): bool
    {
        if ($this->temPoligono($request) || $request->pontoDoImovel() === null) {
            return false;
        }

        foreach (FonteEdificacao::cases() as $fonte) {
            if (Settings::enabled("edificacoes_{$fonte->value}")) {
                return true;
            }
        }

        return Settings::enabled('geoserver_enderecamento');
    }

    /**
     * A IA ainda não deu resposta definitiva e não esgotou as passadas de espera.
     */
    private function iaAindaPodeResponder(ViabilityRequest $request): bool
    {
        $tentativas = LotPolygonResolution::query()
            ->where('viability_request_id', $request->id)
            ->where('source', PoligonoFonte::Ia)
            ->get(['status']);

        $encerrada = $tentativas->contains(fn (LotPolygonResolution $tentativa): bool => in_array(
            $tentativa->status,
            [StatusResolucaoPoligono::NaoResolvido, StatusResolucaoPoligono::Indisponivel, StatusResolucaoPoligono::Encontrado],
            true,
        ));

        $pendentes = $tentativas->where('status', StatusResolucaoPoligono::Pendente)->count();

        return ! $encerrada && $pendentes < (int) config('viabiliza.edificacoes.ia.espera_tentativas', 3);
    }

    /**
     * IA ligada, sem resposta e sem mais espera: fica registrado que o processo
     * seguiu sem ela.
     */
    private function registrarPrazoEsgotado(ViabilityRequest $request, bool $aguardouIa): void
    {
        if ($aguardouIa || ! Settings::enabled('edificacoes_ia')) {
            return;
        }

        $semResposta = ! LotPolygonResolution::query()
            ->where('viability_request_id', $request->id)
            ->where('source', PoligonoFonte::Ia)
            ->whereIn('status', [StatusResolucaoPoligono::NaoResolvido, StatusResolucaoPoligono::Indisponivel])
            ->exists();

        if ($semResposta) {
            $this->complementar->registrar($request, PoligonoFonte::Ia, StatusResolucaoPoligono::NaoResolvido, ['reason' => 'prazo_esgotado']);
        }
    }

    private function temPoligono(ViabilityRequest $request): bool
    {
        return is_array($request->address_polygon_geojson) && $request->address_polygon_geojson !== [];
    }
}
