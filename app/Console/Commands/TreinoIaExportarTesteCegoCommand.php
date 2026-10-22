<?php

namespace App\Console\Commands;

use App\Enums\PoligonoFonte;
use App\Enums\PropertyLocationSource;
use App\Models\ViabilityRequest;
use App\Services\Realty\PropertyCadastroCampos;
use App\Services\Realty\PropertyRegistryLookup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Throwable;

/**
 * Amostra do teste cego da IA de contorno: processos com lote do CODLOG e ponto
 * do Cadastro, sorteados com semente. A entrada da IA (ponto, inscrição,
 * logradouro, área do terreno e se é unidade) e a referência (o lote gravado
 * no processo) vão para arquivos separados; nenhum dado pessoal do Cadastro é
 * gravado.
 */
class TreinoIaExportarTesteCegoCommand extends Command
{
    protected $signature = 'treino-ia:exportar-teste-cego
        {--semente= : Semente do sorteio, registrada no relatório}
        {--quantidade=60 : Quantos processos sortear}';

    protected $description = 'Exporta a amostra do teste cego da IA de contorno de lote, com a referência em arquivo separado';

    private const PADRAO_UNIDADE = '/\b(SALA|APTO|AP|LOJA|BLOCO)\b/i';

    public function handle(PropertyRegistryLookup $cadastro): int
    {
        if ($this->option('semente') === null || ! is_numeric($this->option('semente'))) {
            $this->error('Informe --semente com um número inteiro.');

            return self::FAILURE;
        }

        $ids = ViabilityRequest::query()
            ->where('address_polygon_source', PoligonoFonte::Chave)
            ->whereNotNull('address_polygon_geojson')
            ->where('property_location_source', PropertyLocationSource::CadastroImobiliario)
            ->whereNotNull('property_point_lat')
            ->whereNotNull('property_point_lng')
            ->whereNull('property_polygon_geojson')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $sorteio = new Randomizer(new Mt19937((int) $this->option('semente')));
        $escolhidos = array_slice($sorteio->shuffleArray($ids), 0, max(0, (int) $this->option('quantidade')));

        $entrada = [];
        $referencia = [];

        foreach (ViabilityRequest::query()->whereIn('id', $escolhidos)->get()->sortBy(fn (ViabilityRequest $p): int|false => array_search($p->id, $escolhidos, true)) as $processo) {
            $campos = $this->campos($cadastro, (string) $processo->property_registration);
            $entrada[] = [
                'id' => (string) $processo->id,
                'protocolo' => $processo->protocol_number,
                'inscricao' => $processo->property_registration,
                'codlog' => $processo->cod_log,
                'numero' => $processo->address_number,
                'complemento' => $processo->address_complement,
                'ponto' => [(float) $processo->property_point_lat, (float) $processo->property_point_lng],
                'area_cadastro' => is_numeric($campos?->area_terreno_m2) ? (float) $campos->area_terreno_m2 : null,
                'unidade' => filled($campos?->sub_unidade) || preg_match(self::PADRAO_UNIDADE, (string) $processo->address_complement) === 1,
            ];
            $referencia[(string) $processo->id] = $processo->address_polygon_geojson;
        }

        Storage::disk('local')->put('treino-ia/cego/entrada.json', json_encode($entrada, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        Storage::disk('local')->put('treino-ia/cego/referencia.json', json_encode((object) $referencia, JSON_UNESCAPED_UNICODE));

        $this->info(sprintf('Candidatos: %d. Sorteados: %d (semente %s).', count($ids), count($entrada), $this->option('semente')));

        return self::SUCCESS;
    }

    private function campos(PropertyRegistryLookup $cadastro, string $inscricao): ?PropertyCadastroCampos
    {
        if ($inscricao === '') {
            return null;
        }

        try {
            return $cadastro->resolve($inscricao)->cadastro;
        } catch (Throwable) {
            return null;
        }
    }
}
