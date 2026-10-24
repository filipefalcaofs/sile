<?php

namespace App\Console\Commands;

use App\Enums\ConfirmacaoPonto;
use App\Enums\PoligonoFonte;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Geo\CamadasIncidentesService;
use App\Services\Geo\DistanciaGeografica;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Geo\ZonaProcessoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Corrige os processos em aberto cujo ponto gravado não combina com a rua do
 * endereço da inscrição (o ponto do Cadastro Imobiliário caía em outra rua e o
 * lote, a zona e as camadas saíram de lá). Usa a mesma conferência do
 * protocolo: só mexe onde ela troca o ponto e o ponto gravado está longe do
 * correto. Grava o ponto certo, refaz o lote que veio do ponto, recalcula
 * zona, camadas e classe da via. Decisão, situação, polígono desenhado e
 * ajustes manuais de zona e via não mudam. Quando nenhuma fonte confirma o
 * ponto, o processo só é listado. Sem `--aplicar`, só mostra o levantamento.
 */
class CorrigirPontoInscricaoCommand extends Command
{
    private const TOLERANCIA_M = 5.0;

    protected $signature = 'processos:corrigir-ponto-inscricao
        {--processo= : Corrige só este processo (id)}
        {--desde= : Só processos protocolados a partir desta data (AAAA-MM-DD)}
        {--aplicar : Grava a correção (sem esta opção, só lista)}';

    protected $description = 'Corrige ponto, lote e zona dos processos em aberto cujo ponto gravado não bate com a rua da inscrição';

    public function handle(
        LocalizacaoInscricaoService $localizacao,
        TerritorioProcessoService $territorio,
        PoligonoEnderecamentoService $poligono,
        ZonaProcessoService $zona,
        CamadasIncidentesService $camadas,
        AuditService $audit,
    ): int {
        $aplicar = (bool) $this->option('aplicar');

        $query = ViabilityRequest::query()
            ->whereIn('status', array_map(
                static fn (ViabilityRequestStatus $status): string => $status->value,
                [
                    ViabilityRequestStatus::Protocolada,
                    ViabilityRequestStatus::AguardandoBap,
                    ViabilityRequestStatus::EmAnalise,
                    ViabilityRequestStatus::EmPendencia,
                ],
            ))
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '')
            ->whereNotNull('property_point_lat')
            ->whereNotNull('property_point_lng');

        if ($this->option('processo') !== null) {
            $query->whereKey((int) $this->option('processo'));
        }

        if ($this->option('desde') !== null) {
            $query->where('protocoled_at', '>=', $this->option('desde'));
        }

        $verificados = 0;
        $erros = 0;
        $divergentes = [];
        $semFonte = [];
        $corrigidos = [];

        $query->orderBy('id')->chunkById(100, function ($processos) use ($localizacao, $territorio, $poligono, $zona, $camadas, $audit, $aplicar, &$verificados, &$erros, &$divergentes, &$semFonte, &$corrigidos): void {
            foreach ($processos as $processo) {
                if (is_array($processo->property_polygon_geojson) && $processo->property_polygon_geojson !== []) {
                    continue;
                }

                $verificados++;
                $inscricao = $localizacao->localizar($processo->property_registration);

                if ($inscricao->status !== LocalizacaoInscricao::LOCALIZADA) {
                    continue;
                }

                if ($inscricao->confirmacaoPonto === ConfirmacaoPonto::NaoConfirmado) {
                    $semFonte[] = [
                        $processo->id,
                        $processo->protocol_number,
                        $processo->status->label(),
                        $this->metros($inscricao->distanciaPontoM),
                    ];

                    continue;
                }

                if ($inscricao->confirmacaoPonto !== ConfirmacaoPonto::Substituido || ! $this->pontoGravadoDifere($processo, $inscricao)) {
                    continue;
                }

                $divergentes[] = [
                    $processo->id,
                    $processo->protocol_number,
                    $processo->status->label(),
                    $this->metros($inscricao->distanciaPontoM).' → '.$this->metros($inscricao->distanciaFinalPontoM),
                    $inscricao->origemPonto?->label(),
                    $processo->address_polygon_source?->value ?? 'sem',
                    (string) $processo->zona_codigo,
                ];

                if (! $aplicar) {
                    continue;
                }

                $antes = $this->retrato($processo);

                try {
                    DB::transaction(fn () => $this->corrigir($processo, $inscricao, $territorio, $poligono, $zona, $camadas));
                } catch (Throwable $exception) {
                    $erros++;
                    $this->error("Processo {$processo->id}: {$exception->getMessage()}");

                    continue;
                }

                $depois = $this->retrato($processo->refresh());

                $audit->log(
                    logName: 'geo',
                    event: 'ponto-inscricao-corrigido',
                    description: "Ponto da inscrição corrigido (fonte {$depois['fonte_ponto']}) na solicitação #{$processo->id}",
                    properties: [
                        'viability_request_id' => $processo->id,
                        'inscricao' => $processo->property_registration,
                        'antes' => $antes,
                        'depois' => $depois,
                    ],
                    subject: $processo,
                );

                $corrigidos[] = [
                    $processo->id,
                    $processo->protocol_number,
                    ($antes['poligono_fonte'] ?? 'sem').' → '.($depois['poligono_fonte'] ?? 'sem'),
                    "{$antes['zona_codigo']} → {$depois['zona_codigo']}",
                ];
            }
        });

        $this->info("Verificados: {$verificados}");

        if ($divergentes !== []) {
            $this->table(['Processo', 'Protocolo', 'Situação', 'Distância da rua', 'Ponto novo', 'Polígono', 'Zona atual'], $divergentes);
        }

        if ($semFonte !== []) {
            $this->table(['Processo', 'Protocolo', 'Situação', 'Distância da rua'], $semFonte);
        }

        $this->info('Divergentes: '.count($divergentes));
        $this->info('Sem fonte que confirme: '.count($semFonte));

        if (! $aplicar) {
            $this->info('Simulação: nada foi gravado. Rode com --aplicar para corrigir.');

            return self::SUCCESS;
        }

        if ($corrigidos !== []) {
            $this->table(['Processo', 'Protocolo', 'Polígono', 'Zona'], $corrigidos);
        }

        $this->info('Corrigidos: '.count($corrigidos));
        $this->info("Com erro: {$erros}");

        return self::SUCCESS;
    }

    private function pontoGravadoDifere(ViabilityRequest $processo, LocalizacaoInscricao $inscricao): bool
    {
        if ($inscricao->lat === null || $inscricao->lng === null) {
            return false;
        }

        return DistanciaGeografica::entrePontos(
            (float) $processo->property_point_lat,
            (float) $processo->property_point_lng,
            $inscricao->lat,
            $inscricao->lng,
        ) > self::TOLERANCIA_M;
    }

    /**
     * Mesma ordem do protocolo. O lote tirado do ponto antigo é descartado
     * antes de refazer a busca: se ela não achar lote no ponto certo (ou
     * estiver fora do ar), fica sem polígono e a zona volta a ser a do ponto,
     * em vez de manter o lote da rua errada. Lote da chave ou do SEMAP não
     * depende do ponto e fica.
     */
    private function corrigir(
        ViabilityRequest $processo,
        LocalizacaoInscricao $inscricao,
        TerritorioProcessoService $territorio,
        PoligonoEnderecamentoService $poligono,
        ZonaProcessoService $zona,
        CamadasIncidentesService $camadas,
    ): void {
        $fonteDoLote = $processo->address_polygon_source;
        $semLote = ! is_array($processo->address_polygon_geojson) || $processo->address_polygon_geojson === [];

        $processo->forceFill([
            'property_point_lat' => $inscricao->lat,
            'property_point_lng' => $inscricao->lng,
            'property_location_source' => $inscricao->origemPonto,
        ])->save();

        if ($fonteDoLote === PoligonoFonte::Ponto) {
            $processo->forceFill([
                'address_polygon_geojson' => null,
                'address_polygon_source' => null,
            ])->save();
            $semLote = true;
        }

        if ($semLote) {
            $poligono->refazer($processo);
        }

        // A materialização pelo ponto pode trocar o CODLOG pela via mais
        // próxima: o da inscrição volta logo depois.
        $territorio->materializar($processo);
        $territorio->aplicarLogradouroDaInscricao($processo);
        $camadas->materializar($processo);
        $zona->materializar($processo);
        $territorio->registrarClasseViaria($processo);
    }

    /**
     * @return array{ponto: array{lat: ?float, lng: ?float}, fonte_ponto: ?string, cod_log: string, poligono_fonte: ?string, zona_codigo: string, zona_fonte: ?string, via: string}
     */
    private function retrato(ViabilityRequest $processo): array
    {
        return [
            'ponto' => [
                'lat' => $processo->property_point_lat !== null ? (float) $processo->property_point_lat : null,
                'lng' => $processo->property_point_lng !== null ? (float) $processo->property_point_lng : null,
            ],
            'fonte_ponto' => $processo->property_location_source?->value,
            'cod_log' => (string) $processo->cod_log,
            'poligono_fonte' => is_array($processo->address_polygon_geojson) && $processo->address_polygon_geojson !== []
                ? (string) $processo->address_polygon_source?->value
                : null,
            'zona_codigo' => (string) $processo->zona_codigo,
            'zona_fonte' => $processo->zona_fonte?->value,
            'via' => (string) ($processo->camadas_incidentes['via'] ?? ''),
        ];
    }

    private function metros(?float $valor): string
    {
        return $valor === null ? '—' : number_format($valor, 0, ',', '.').' m';
    }
}
