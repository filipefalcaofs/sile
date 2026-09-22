<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro10ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga do Quadro 10 da LOUOS (permissão por zona — HU-016/HU-039): publica uma
 * versão vigente do domínio louos_quadro10 com a matriz oficial da Lei nº
 * 9.148/2016 (1.323 células em 21 zonas). Delega o import ao service e audita
 * o relatório (RN-002). Sem a zona do território o motor degrada para pendente
 * — nunca inventa permissão.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa a
 * versão vigente se já existir e substitui as linhas dessa versão antes do
 * import — re-seed não deixa órfãs nem duplica.
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
                'Lei nº 9.148/2016 — Quadro 10 (permissão por zona), matriz oficial',
            );

            $version = $rules->publish($draft);
        }

        LouosQuadro10Permissao::query()->where('rule_version_id', $version->getKey())->delete();

        $report = app(LouosQuadro10ImportService::class)->import(
            $version,
            database_path('data/louos/oficial/quadro10-permissoes.csv'),
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: 'importacao-quadro10',
            description: 'Importação do Quadro 10 da LOUOS (matriz oficial da Lei 9.148/2016)',
            properties: $report,
            rulesVersion: 'lei-9148-2016-quadro10',
        );
    }
}
