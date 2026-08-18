<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro7ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga do Quadro 7 da LOUOS (HU-015/HU-038): publica uma versão vigente do
 * domínio louos_quadro7 derivada da Lei nº 9.148/2016 (modelo "Enquadramento
 * TVL" do SAPS legado — atividade → faixa de área → grupo/subgrupo de uso),
 * delega o import real ao service e registra o relatório na trilha de auditoria
 * com a versão de regras (RN-002). SUBSTITUÍVEL pela planilha oficial do Quadro
 * 7 da SEDUR sem mudar a lógica — muda a carga, não o motor.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa a
 * versão vigente se já existir (não republica) e o import faz upsert por
 * (rule_version_id, cnae_code, area_min) — re-seed não duplica.
 */
class LouosQuadro7Seeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::LouosQuadro7,
                'lei-9148-2016-quadro7',
                'Lei nº 9.148/2016 — Quadro 7 (enquadramento por área), derivado do modelo TVL/SAPS',
            );

            $version = $rules->publish($draft);
        }

        $report = app(LouosQuadro7ImportService::class)->import(
            $version,
            database_path('data/louos/quadro7-faixas.csv'),
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: 'importacao-quadro7',
            description: 'Importação do Quadro 7 da LOUOS (enquadramento por área — Lei 9.148/2016)',
            properties: $report,
            rulesVersion: 'lei-9148-2016-quadro7',
        );
    }
}
