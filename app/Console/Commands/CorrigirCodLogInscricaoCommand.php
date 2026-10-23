<?php

namespace App\Console\Commands;

use App\Enums\PoligonoFonte;
use App\Enums\ViabilityRequestStatus;
use App\Models\ViabilityRequest;
use App\Services\Geo\GeoServerEnderecamentoClient;
use App\Services\Geo\PoligonoEnderecamentoService;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Geo\ZonaProcessoService;
use App\Services\Realty\LocalizacaoInscricao;
use App\Services\Realty\LocalizacaoInscricaoService;
use App\Support\Audit\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;
use Throwable;

/**
 * Corrige os processos protocolados com o CODLOG tributário no lugar do
 * endereço de Localização da inscrição: grava o CODLOG certo, refaz a busca do
 * lote (chave correta, SEMAP e lote que contém o ponto do imóvel), recalcula
 * a zona e a classe da via. Decisão, situação e ajustes manuais de zona e via
 * não mudam. Sem `--aplicar`, só mostra o levantamento. `--reparar-lotes`
 * devolve o lote, pelo ponto, aos processos que a primeira versão desta
 * correção deixou sem polígono.
 */
class CorrigirCodLogInscricaoCommand extends Command
{
    protected $signature = 'processos:corrigir-codlog-inscricao
        {--processo= : Corrige só este processo}
        {--desde= : Só processos protocolados a partir desta data (AAAA-MM-DD)}
        {--aplicar : Grava a correção (sem esta opção, só simula)}
        {--reparar-lotes : Refaz o lote dos processos que perderam o polígono na correção}';

    protected $description = 'Corrige CODLOG, via, polígono e zona dos processos gravados com o logradouro tributário';

    public function handle(
        LocalizacaoInscricaoService $localizacao,
        TerritorioProcessoService $territorio,
        GeoServerEnderecamentoClient $enderecamento,
        PoligonoEnderecamentoService $poligono,
        ZonaProcessoService $zona,
        AuditService $audit,
    ): int {
        $filtros = array_filter([
            '--processo' => $this->option('processo'),
            '--desde' => $this->option('desde'),
        ], fn (mixed $valor): bool => $valor !== null);

        if ($this->option('reparar-lotes')) {
            return $this->repararLotes($territorio, $poligono, $zona, $audit);
        }

        if (! $this->option('aplicar')) {
            $this->call('processos:auditar-codlog-inscricao', $filtros);
            $this->info('Simulação: nada foi gravado. Rode com --aplicar para corrigir.');

            return self::SUCCESS;
        }

        $query = ViabilityRequest::query()
            ->where('status', '!=', ViabilityRequestStatus::Rascunho)
            ->whereNotNull('property_registration')
            ->where('property_registration', '!=', '');

        if (isset($filtros['--processo'])) {
            $query->whereKey((int) $filtros['--processo']);
        }

        if (isset($filtros['--desde'])) {
            $query->where('protocoled_at', '>=', $filtros['--desde']);
        }

        $corrigidos = [];
        $erros = 0;

        $query->chunkById(100, function ($processos) use ($localizacao, $territorio, $enderecamento, $poligono, $zona, $audit, &$corrigidos, &$erros): void {
            foreach ($processos as $processo) {
                $inscricao = $localizacao->localizar($processo->property_registration);
                $correto = $inscricao->codLogDoEndereco();

                if ($inscricao->status === LocalizacaoInscricao::INDISPONIVEL
                    || $correto === null
                    || $correto === ltrim(trim((string) $processo->cod_log), '0')) {
                    continue;
                }

                $antes = $this->retrato($processo);

                try {
                    DB::transaction(fn () => $this->corrigir($processo, $territorio, $enderecamento, $poligono, $zona));
                } catch (Throwable $exception) {
                    $erros++;
                    $this->error("Processo {$processo->id}: {$exception->getMessage()}");

                    continue;
                }

                $depois = $this->retrato($processo->refresh());

                $audit->log(
                    logName: 'geo',
                    event: 'codlog-inscricao-corrigido',
                    description: "CODLOG {$antes['cod_log']} corrigido para {$depois['cod_log']} (endereço de Localização da inscrição) na solicitação #{$processo->id}",
                    properties: ['viability_request_id' => $processo->id, 'antes' => $antes, 'depois' => $depois],
                    subject: $processo,
                );

                $corrigidos[] = [
                    $processo->id,
                    $processo->protocol_number,
                    "{$antes['cod_log']} → {$depois['cod_log']}",
                    "{$antes['via']} → {$depois['via']}",
                    ($antes['poligono_fonte'] ?? 'sem').' → '.($depois['poligono_fonte'] ?? 'sem'),
                    "{$antes['zona_codigo']} → {$depois['zona_codigo']}",
                ];
            }
        });

        if ($corrigidos !== []) {
            $this->table(['Processo', 'Protocolo', 'CODLOG', 'Via', 'Polígono', 'Zona'], $corrigidos);
        }

        $this->info('Corrigidos: '.count($corrigidos));
        $this->info("Com erro: {$erros}");

        return self::SUCCESS;
    }

    /**
     * Mesma ordem do protocolo. O lote é refeito quando a chave correta acha
     * lote, quando o atual veio da chave errada ou quando não há lote; ele só
     * sai se a busca pelo ponto responder que não há lote ali, e aí a zona
     * volta a ser a do ponto.
     */
    private function corrigir(
        ViabilityRequest $processo,
        TerritorioProcessoService $territorio,
        GeoServerEnderecamentoClient $enderecamento,
        PoligonoEnderecamentoService $poligono,
        ZonaProcessoService $zona,
    ): void {
        $fonte = $processo->address_polygon_source;

        $territorio->aplicarLogradouroDaInscricao($processo);

        $numero = trim((string) $processo->address_number);
        $loteNaChave = $numero !== '' && $enderecamento->consultar((string) $processo->cod_log, $numero, $processo->address_complement)->encontrado();

        $semLote = ! is_array($processo->address_polygon_geojson) || $processo->address_polygon_geojson === [];

        if ($loteNaChave || $fonte === PoligonoFonte::Chave || $semLote) {
            $this->refazerLote($processo, $territorio, $poligono);
        }

        $zona->materializar($processo);
        $territorio->registrarClasseViaria($processo);
    }

    private function refazerLote(ViabilityRequest $processo, TerritorioProcessoService $territorio, PoligonoEnderecamentoService $poligono): string
    {
        if ($processo->pontoGravadoOuDesenhado() === null) {
            $territorio->localizarPelaInscricao($processo);
        }

        $resultado = $poligono->refazer($processo);

        if ($resultado === 'removido') {
            // A materialização pelo ponto pode trocar o CODLOG pela via mais
            // próxima: o da inscrição volta logo depois.
            $territorio->materializar($processo);
            $territorio->aplicarLogradouroDaInscricao($processo);
        }

        return $resultado;
    }

    /**
     * Processos que a correção deixou sem polígono (antes da chave, depois sem
     * lote) e que continuam sem: refaz a cadeia do lote, agora com o ponto.
     */
    private function repararLotes(TerritorioProcessoService $territorio, PoligonoEnderecamentoService $poligono, ZonaProcessoService $zona, AuditService $audit): int
    {
        $ids = Activity::query()
            ->where('event', 'codlog-inscricao-corrigido')
            ->where('subject_type', (new ViabilityRequest)->getMorphClass())
            ->get()
            ->filter(fn (Activity $registro): bool => ($registro->properties['antes']['poligono_fonte'] ?? null) === PoligonoFonte::Chave->value
                && ($registro->properties['depois']['poligono_fonte'] ?? null) === null)
            ->pluck('subject_id')
            ->unique()
            ->all();

        $linhas = [];
        $reparados = 0;

        foreach (ViabilityRequest::query()->whereKey($ids)->orderBy('id')->get() as $processo) {
            if (is_array($processo->address_polygon_geojson) && $processo->address_polygon_geojson !== []) {
                continue;
            }

            $antes = $this->retrato($processo);

            try {
                $resultado = DB::transaction(function () use ($processo, $territorio, $poligono, $zona): string {
                    $resultado = $this->refazerLote($processo, $territorio, $poligono);
                    $zona->materializar($processo);

                    return $resultado;
                });
            } catch (Throwable $exception) {
                $this->error("Processo {$processo->id}: {$exception->getMessage()}");

                continue;
            }

            $depois = $this->retrato($processo->refresh());

            if ($depois['poligono_fonte'] !== null) {
                $reparados++;
                $audit->log(
                    logName: 'geo',
                    event: 'codlog-lote-reparado',
                    description: "Lote refeito pela fonte {$depois['poligono_fonte']} na solicitação #{$processo->id}",
                    properties: ['viability_request_id' => $processo->id, 'antes' => $antes, 'depois' => $depois],
                    subject: $processo,
                );
            }

            $linhas[] = [$processo->id, $processo->protocol_number, $resultado, "{$antes['zona_codigo']} → {$depois['zona_codigo']}"];
        }

        if ($linhas !== []) {
            $this->table(['Processo', 'Protocolo', 'Lote', 'Zona'], $linhas);
        }

        $this->info("Lotes reparados: {$reparados}");

        return self::SUCCESS;
    }

    /**
     * @return array{cod_log: string, via: string, poligono_fonte: ?string, zona_codigo: string, zona_fonte: ?string}
     */
    private function retrato(ViabilityRequest $processo): array
    {
        return [
            'cod_log' => (string) $processo->cod_log,
            'via' => (string) ($processo->camadas_incidentes['via'] ?? ''),
            'poligono_fonte' => is_array($processo->address_polygon_geojson) && $processo->address_polygon_geojson !== []
                ? (string) $processo->address_polygon_source?->value
                : null,
            'zona_codigo' => (string) $processo->zona_codigo,
            'zona_fonte' => $processo->zona_fonte?->value,
        ];
    }
}
