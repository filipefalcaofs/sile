<?php

namespace Database\Seeders;

use App\Enums\TipoGatilho;
use App\Models\RiskTrigger;
use Illuminate\Database\Seeder;

/**
 * Catálogo dos gatilhos de risco conhecidos (categoria semi-expresso) — os 3
 * do CONTEXT da Fase 6 (HU-049/HU-051). Upsert por `codigo` APENAS dos
 * metadados (titulo/motivo/categoria): `ativo` NUNCA entra no array de update,
 * espelhando o ParameterSeeder/RolesAndPermissionsSeeder — re-seed em deploy
 * preserva o liga/desliga administrado (HU-014). Na criação, `ativo` usa o
 * default da coluna (true).
 */
class RiskTriggerSeeder extends Seeder
{
    public function run(): void
    {
        foreach (self::triggers() as $codigo => $meta) {
            RiskTrigger::query()->updateOrCreate(['codigo' => $codigo], $meta);
        }
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function triggers(): array
    {
        return [
            TipoGatilho::EnquadramentoAusente->value => [
                'titulo' => 'Enquadramento locacional ausente',
                'motivo' => 'CNAE sem classificação de risco vigente na base — encaminhado à análise técnica para enquadramento manual antes de qualquer deferimento.',
                'categoria' => 'semi_expresso',
            ],
            TipoGatilho::ZeisEspecial->value => [
                'titulo' => 'Localização em ZEIS',
                'motivo' => 'Imóvel em Zona Especial de Interesse Social (ZEIS) exige análise técnica específica conforme a LOUOS (Lei nº 9.148/2016) — o fluxo expresso não se aplica.',
                'categoria' => 'semi_expresso',
            ],
            TipoGatilho::DadosDoProcesso->value => [
                'titulo' => 'Dados do processo exigem análise',
                'motivo' => 'Informações declaradas no processo (porte, área utilizada ou condicionantes respondidas) demandam análise técnica antes do deferimento.',
                'categoria' => 'semi_expresso',
            ],
        ];
    }
}
