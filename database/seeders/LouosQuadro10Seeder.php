<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro10ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga do Quadro 10 da LOUOS (permissão por zona — HU-016/HU-039): publica uma
 * versão vigente do domínio louos_quadro10 MODELADA a partir da Lei nº
 * 9.148/2016 (carga oficial por zona pendente SEDUR — SIGIS/CA 2000), delega o
 * import real ao service e audita o relatório com a versão de regras (RN-002).
 * O motor degrada para pendente sem a zona real (05-04) — nunca inventa
 * permissão. SUBSTITUÍVEL pela carga oficial sem mudar a lógica.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa a
 * versão vigente se já existir e o import faz upsert — re-seed não duplica.
 */
class LouosQuadro10Seeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::LouosQuadro10,
                'lei-9148-2016-quadro10',
                'Lei nº 9.148/2016 — Quadro 10 (permissão por zona), modelado — carga oficial pendente SEDUR',
            );

            $version = $rules->publish($draft);
        }

        $report = app(LouosQuadro10ImportService::class)->import(
            $version,
            database_path('data/louos/quadro10-permissoes.csv'),
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: 'importacao-quadro10',
            description: 'Importação do Quadro 10 da LOUOS (permissão por zona — modelado da Lei 9.148/2016)',
            properties: $report,
            rulesVersion: 'lei-9148-2016-quadro10',
        );
    }
}
