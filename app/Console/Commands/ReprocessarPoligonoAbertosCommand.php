<?php

namespace App\Console\Commands;

use App\Enums\AnalysisRecordStatus;
use App\Enums\FonteEdificacao;
use App\Enums\StatusResolucaoPoligono;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Geo\Edificacoes\BuildingFootprintRepository;
use App\Services\Geo\Edificacoes\PoligonoComplementarService;
use App\Services\Geo\ZonaMaiorPorcaoResolver;
use App\Services\Geo\ZonaProcessoService;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Polígono pelas edificações complementares nos processos que entraram antes
 * de a fonte ser ligada e seguem em análise. Só grava o polígono e recalcula a
 * zona pela maior porção: decisão, encaminhamento e analista não mudam.
 * Processo decidido, encerrado ou com ficha de análise finalizada fica de fora.
 */
class ReprocessarPoligonoAbertosCommand extends Command
{
    protected $signature = 'geo:poligono:reprocessar-abertos
        {--dry-run : Consulta a base e calcula a zona sem gravar nada}';

    protected $description = 'Busca o polígono pelas edificações complementares nos processos em análise sem polígono e recalcula a zona pela maior porção';

    public function handle(
        PoligonoComplementarService $complementar,
        ZonaProcessoService $zona,
        BuildingFootprintRepository $base,
        ZonaMaiorPorcaoResolver $maiorPorcao,
        AuditService $audit,
    ): int {
        $fontes = array_values(array_filter(
            [FonteEdificacao::Overture, FonteEdificacao::Microsoft],
            fn (FonteEdificacao $fonte): bool => Settings::enabled("edificacoes_{$fonte->value}"),
        ));

        if ($fontes === []) {
            $this->error('Nenhuma fonte de edificações ligada (Overture ou Microsoft) no painel de integrações.');

            return self::FAILURE;
        }

        $simular = (bool) $this->option('dry-run');
        $contadores = ['vistos' => 0, 'com_poligono' => 0, 'sem_ponto' => 0, 'ponto_inelegivel' => 0, 'sem_predio' => 0];
        $mudancas = [];

        $this->elegiveis()->chunkById(100, function (Collection $processos) use ($simular, $fontes, $complementar, $zona, $base, $maiorPorcao, &$contadores, &$mudancas): void {
            foreach ($processos as $processo) {
                $contadores['vistos']++;

                if ($processo->pontoDoImovel() === null) {
                    $contadores['sem_ponto']++;

                    continue;
                }

                if (! $processo->pontoElegivelParaEdificacao()) {
                    $contadores['ponto_inelegivel']++;

                    continue;
                }

                $zonaAntes = $processo->zona_codigo;
                $resultado = $simular
                    ? $this->simular($processo, $fontes, $base, $maiorPorcao)
                    : $this->aplicar($processo, $complementar, $zona);

                if ($resultado === null) {
                    $contadores['sem_predio']++;

                    continue;
                }

                $contadores['com_poligono']++;

                if ($resultado['zona'] !== $zonaAntes) {
                    $mudancas[] = [$processo->id, $processo->protocol_number ?? '—', $zonaAntes ?? '—', $resultado['zona'] ?? '—', $resultado['fonte']];
                }
            }
        });

        $this->relatar($simular, $contadores, $mudancas);

        if (! $simular) {
            $audit->log(
                logName: 'geo',
                event: 'poligono-complementar-reprocesso',
                description: 'Polígono pelas edificações complementares nos processos em análise sem polígono',
                properties: [...$contadores, 'zona_alterada' => array_column($mudancas, 0)],
                result: 'sucesso',
            );
        }

        return self::SUCCESS;
    }

    /**
     * @return Builder<ViabilityRequest>
     */
    private function elegiveis(): Builder
    {
        return ViabilityRequest::query()
            ->whereIn('status', [ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::EmPendencia])
            ->whereNull('address_polygon_geojson')
            ->whereDoesntHave('analysisRecords', fn (Builder $ficha) => $ficha->where('status', AnalysisRecordStatus::Finalizada));
    }

    /**
     * @return array{fonte: string, zona: ?string}|null
     */
    private function aplicar(ViabilityRequest $processo, PoligonoComplementarService $complementar, ZonaProcessoService $zona): ?array
    {
        $resultado = $complementar->resolver($processo, aguardarIa: false);

        if ($resultado->status !== StatusResolucaoPoligono::Encontrado) {
            return null;
        }

        $zona->materializar($processo->refresh());

        return ['fonte' => (string) $resultado->fonte?->value, 'zona' => $processo->refresh()->zona_codigo];
    }

    /**
     * @param  list<FonteEdificacao>  $fontes
     * @return array{fonte: string, zona: ?string}|null
     */
    private function simular(ViabilityRequest $processo, array $fontes, BuildingFootprintRepository $base, ZonaMaiorPorcaoResolver $maiorPorcao): ?array
    {
        $ponto = (array) $processo->pontoDoImovel();

        foreach ($fontes as $fonte) {
            $candidata = $base->contendoPonto($fonte, (float) $ponto['lat'], (float) $ponto['lng']);

            if ($candidata === null) {
                continue;
            }

            $calculo = $maiorPorcao->resolver($candidata->geojson);

            return ['fonte' => $fonte->value, 'zona' => $calculo->identificado() ? $calculo->codigo : $processo->zona_codigo];
        }

        return null;
    }

    /**
     * @param  array<string, int>  $contadores
     * @param  list<array{0: int, 1: string, 2: string, 3: string, 4: string}>  $mudancas
     */
    private function relatar(bool $simular, array $contadores, array $mudancas): void
    {
        if ($simular) {
            $this->warn('Simulação: nada foi gravado.');
        }

        $this->info("Processos vistos: {$contadores['vistos']}");
        $this->info("Com polígono: {$contadores['com_poligono']}");
        $this->info("Sem ponto: {$contadores['sem_ponto']}");
        $this->info("Ponto inelegível: {$contadores['ponto_inelegivel']}");
        $this->info("Sem prédio no ponto: {$contadores['sem_predio']}");

        if ($mudancas === []) {
            $this->info('Nenhum processo mudou de zona.');

            return;
        }

        $this->info('Processos que mudaram de zona:');
        $this->table(['Processo', 'Protocolo', 'Zona antes', 'Zona nova', 'Fonte'], $mudancas);
    }
}
