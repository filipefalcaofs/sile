<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro11ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga do Quadro 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041): publica UMA versão vigente (domínio
 * louos_quadro11a) a partir do CSV quadro11a-condicoes-via.csv. O "Quadro 11"
 * não existe na publicação oficial da SEDUR — apenas 11A e 11B. Dado
 * MODELADO da Lei nº 9.148/2016 (atributo viário real pendente confirmação
 * SEDUR). SUBSTITUÍVEL pela carga oficial sem mudar a lógica.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa a
 * versão vigente se já existir e o import faz upsert — re-seed não duplica.
 */
class LouosQuadro11Seeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::LouosQuadro11a,
                'lei-9148-2016-quadro11a',
                'Lei nº 9.148/2016 — Quadro 11A da LOUOS (condições complementares pela via — modelado)',
            );

            $version = $rules->publish($draft);
        }

        $report = app(LouosQuadro11ImportService::class)->import(
            $version,
            database_path('data/louos/quadro11a-condicoes-via.csv'),
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: 'importacao-quadro11a',
            description: 'Importação do Quadro 11A da LOUOS (condições complementares pela via — modelado da Lei 9.148/2016)',
            properties: $report,
            rulesVersion: 'lei-9148-2016-quadro11a',
        );
    }
}
