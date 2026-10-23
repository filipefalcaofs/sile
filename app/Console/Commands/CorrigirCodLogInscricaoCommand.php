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
use Throwable;

/**
 * Corrige os processos protocolados com o CODLOG tributário no lugar do
 * endereço de Localização da inscrição: grava o CODLOG certo, refaz a busca do
 * lote pela chave correta (o lote achado pela chave errada sai), recalcula a
 * zona e a classe da via. Decisão, situação e ajustes manuais de zona e via
 * não mudam. Sem `--aplicar`, só mostra o levantamento.
 */
class CorrigirCodLogInscricaoCommand extends Command
{
    protected $signature = 'processos:corrigir-codlog-inscricao
        {--processo= : Corrige só este processo}
        {--desde= : Só processos protocolados a partir desta data (AAAA-MM-DD)}
        {--aplicar : Grava a correção (sem esta opção, só simula)}';

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
     * Mesma ordem do protocolo. O lote achado pela chave errada sai quando a
     * chave correta e o SEMAP não acham outro; aí a zona volta a ser a do ponto.
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

        if ($loteNaChave || $fonte === PoligonoFonte::Chave) {
            $achou = $poligono->aplicar($processo);

            if (! $achou && $fonte === PoligonoFonte::Chave) {
                $processo->forceFill([
                    'address_polygon_geojson' => null,
                    'address_polygon_source' => null,
                    'address_polygon_consulted_at' => now(),
                ])->save();

                if ($processo->pontoGravadoOuDesenhado() === null) {
                    $territorio->localizarPelaInscricao($processo);
                }

                // A materialização pelo ponto pode trocar o CODLOG pela via mais
                // próxima: o da inscrição volta logo depois.
                $territorio->materializar($processo);
                $territorio->aplicarLogradouroDaInscricao($processo);
            }
        }

        $zona->materializar($processo);
        $territorio->registrarClasseViaria($processo);
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
