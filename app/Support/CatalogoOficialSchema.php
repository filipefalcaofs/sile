<?php

namespace App\Support;

use App\Models\Sector;
use App\Models\TratamentoCondicionante;
use Illuminate\Support\Facades\Schema;

/**
 * A migration de carga do catálogo (2026_09_23) ficou no meio de migrations
 * de schema posteriores (condicionantes, code de setor). Só semear quando
 * essas tabelas/colunas existirem — senão a carga seguinte assume o trabalho.
 */
final class CatalogoOficialSchema
{
    public static function pronto(): bool
    {
        return Schema::hasTable((new TratamentoCondicionante)->getTable())
            && Schema::hasTable('treatment_cnlu_itens')
            && Schema::hasTable('treatment_notas')
            && Schema::hasTable('treatment_pendencias')
            && Schema::hasColumn('rule_versions', 'content_hash')
            && Schema::hasColumn('treatment_enquadramentos', 'vagas')
            && Schema::hasColumn('treatment_regra_ramos', 'bloqueia')
            && Schema::hasColumn((new Sector)->getTable(), 'code')
            && Schema::hasTable('user_sector_assignments');
    }
}
