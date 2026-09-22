<?php

namespace Database\Seeders;

use App\Models\GeoServerLayer;
use Illuminate\Database\Seeder;

/**
 * Carga inicial = as 20 FeatureTypes de zona da LOUOS que viviam hardcoded
 * em config/sile.php (integrations.geoserver.type_names). Idempotente por
 * workspace+type_name via firstOrCreate (padrão RiskTriggerSeeder): o re-seed
 * em deploy só cria as camadas AUSENTES — NUNCA reativa uma camada que o
 * administrador desativou pela UI nem sobrescreve ordem/rótulo editados
 * (HU-014). Na criação, `ativo` usa o default da coluna (true) e `ordem`
 * recebe a posição sequencial desta lista.
 */
class GeoServerLayerSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const CAMADAS = [
        'louos_zpr1:VM_L_Z_USO_ZPR_1',
        'louos_zpr2:VM_L_Z_USO_ZPR_2',
        'louos_zpr3:VM_L_Z_USO_ZPR_3',
        'louos_zpam:VM_L_Z_USO_ZPAM',
        'louos_zde1:VM_L_Z_USO_ZDE_1',
        'louos_zde2:VM_L_Z_USO_ZDE_2',
        'louos_zue:VM_L_Z_USO_ZUE',
        'louos_zusi:VM_L_Z_USO_ZUSI',
        'louos_zem:VM_L_Z_USO_ZEM',
        'louos_zit:VM_L_Z_USO_ZIT',
        'louos_zeis:VM_L_Z_USO_ZEIS',
        'louos_zona_uso_zclme:VM_L_Z_USO_ZCLME',
        'louos_zona_uso_zclmu:VM_L_Z_USO_ZCLMU',
        'louos_zcme_aguas_claras:VM_L_Z_USO_ZCME_AGUAS_CLARAS',
        'louos_zcme_camaragibe:VM_L_Z_USO_ZCME_CAMARAGIBE',
        'louos_zcme_centro_antigo:VM_L_Z_USO_ZCME_CA',
        'louos_zcme_luis_viana_29_marco:VM_L_Z_USO_ZCME_L_VIANA_29_MAR',
        'louos_zcme_retiro_acesso_norte:VM_L_Z_USO_ZCME_RET_ACESS_NOR',
        'louos_zcmu_municipal_1:VM_L_Z_USO_ZCMU_1',
        'louos_zcmu_municipal_2:VM_L_Z_USO_ZCMU_2',
    ];

    public function run(): void
    {
        foreach (self::CAMADAS as $indice => $camada) {
            [$workspace, $typeName] = explode(':', $camada, 2);

            GeoServerLayer::firstOrCreate(
                ['workspace' => $workspace, 'type_name' => $typeName],
                ['ordem' => $indice + 1],
            );
        }
    }
}
