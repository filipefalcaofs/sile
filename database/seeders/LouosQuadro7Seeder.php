<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro7ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga do Quadro 7 da LOUOS (HU-015/HU-038): publica uma versão vigente do
 * domínio louos_quadro7 com a ponte operacional CNAE→uso da planilha 20.08.26
 * (catálogo CNAE-Subclasses 2.3, 1.331 códigos). Delega o import ao service e
 * audita o relatório (RN-002). O PDF do Quadro 7 classifica usos, não CNAE —
 * a planilha é a correspondência operacional; muda a carga, não o motor.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa a
 * versão vigente se já existir (não republica) e substitui as faixas dessa
 * versão antes do import — re-seed não deixa órfãs nem duplica.
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
                'Planilha 20.08.26 — Quadro 7 operacional (CNAE → grupo/subgrupo por faixa de área)',
            );

            $version = $rules->publish($draft);
        }

        LouosQuadro7Faixa::query()->where('rule_version_id', $version->getKey())->delete();

        $report = app(LouosQuadro7ImportService::class)->import(
            $version,
            database_path('data/louos/oficial/quadro7-faixas.csv'),
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: 'importacao-quadro7',
            description: 'Importação do Quadro 7 da LOUOS (ponte operacional CNAE→uso — planilha 20.08.26)',
            properties: $report,
            rulesVersion: 'lei-9148-2016-quadro7',
        );
    }
}
