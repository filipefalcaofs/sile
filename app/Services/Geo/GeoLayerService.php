<?php

namespace App\Services\Geo;

use App\Enums\GeoLayerStatus;
use App\Enums\GeoLayerType;
use App\Models\GeoLayer;
use App\Support\Audit\AuditService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Versionamento de camadas geográficas com vigência (HU-036 RN-004) e diff
 * auditado (RN-005). A carga de uma nova versão NÃO apaga a anterior: a
 * vigente é fechada (status substituída, valid_to preenchido) e a nova nasce
 * vigente — a consulta operacional usa a vigente, a reprodução usa a versão da
 * data da decisão.
 */
class GeoLayerService
{
    public function __construct(private AuditService $audit) {}

    /**
     * Abre uma nova versão da camada, fechando a vigente anterior do mesmo
     * tipo. Idempotente por (type, version): se a carga já existe, devolve-a
     * sem duplicar nem fechar a vigente (a unique constraint protege).
     */
    public function openVersion(
        GeoLayerType $type,
        string $version,
        string $source,
        ?CarbonInterface $validFrom = null,
    ): GeoLayer {
        return DB::transaction(function () use ($type, $version, $source, $validFrom): GeoLayer {
            $existing = GeoLayer::query()
                ->where('type', $type->value)
                ->where('version', $version)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $validFrom ??= Carbon::today();

            GeoLayer::query()
                ->where('type', $type->value)
                ->whereNull('valid_to')
                ->update([
                    'status' => GeoLayerStatus::Substituida->value,
                    'valid_to' => $validFrom,
                ]);

            return GeoLayer::query()->create([
                'type' => $type,
                'version' => $version,
                'status' => GeoLayerStatus::Vigente,
                'valid_from' => $validFrom,
                'valid_to' => null,
                'source' => $source,
                'rules_version' => $version,
                'feature_count' => 0,
            ]);
        });
    }

    /**
     * Atualiza feature_count com a contagem real de geometrias da carga.
     */
    public function finalizeCount(GeoLayer $layer): void
    {
        $layer->update(['feature_count' => $layer->features()->count()]);
    }

    /**
     * Diff de carga (HU-036 RN-005).
     *
     * DECISÃO DOCUMENTADA: sem chave estável por feature na fonte (o GeoJSON do
     * GeoSalvador não traz identificador persistente confiável de feição), a
     * carga de uma nova versão — e o re-import da mesma versão — é contabilizada
     * como remove + add, NUNCA como alteração in-place. A RN-005 exige o resumo
     * de features adicionadas/removidas, que é exatamente o que este diff
     * entrega; "alteradas" fica reservado (sempre 0) para uma evolução futura,
     * quando houver identificador estável de feição. A contagem é pura e
     * consultável.
     *
     * @return array{adicionadas: int, alteradas: int, removidas: int}
     */
    public function computeDiff(?GeoLayer $previous, GeoLayer $current): array
    {
        return [
            'adicionadas' => $current->features()->count(),
            'alteradas' => 0,
            'removidas' => $previous?->features()->count() ?? 0,
        ];
    }

    /**
     * Audita a carga da camada (RN-002 + RN-005): origem, tipo, versão e o diff
     * de feições. O responsável e a origem técnica (ip/user_agent/channel) são
     * preenchidos pela RecordActivityAction via causedBy.
     *
     * @param  array{adicionadas: int, alteradas: int, removidas: int}  $diff
     */
    public function auditLoad(GeoLayer $layer, array $diff): void
    {
        $this->audit->log(
            logName: 'territorio',
            event: 'carga-camada',
            description: "Carga da camada {$layer->type->label()} versão {$layer->version}",
            properties: [
                'origem' => $layer->source,
                'type' => $layer->type->value,
                'version' => $layer->version,
                'diff' => $diff,
            ],
            subject: $layer,
            result: 'sucesso',
            rulesVersion: $layer->version,
        );
    }
}
