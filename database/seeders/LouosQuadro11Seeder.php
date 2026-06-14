<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Services\Louos\LouosQuadro11ImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga dos Quadros 11 e 11A da LOUOS (condições de instalação pela via —
 * HU-017/HU-018/HU-040/HU-041): publica DUAS versões vigentes (domínios
 * louos_quadro11 e louos_quadro11a) a partir do MESMO CSV, filtrando pela coluna
 * `quadro`. Dado MODELADO da Lei nº 9.148/2016 (atributo viário real pendente
 * confirmação SEDUR). SUBSTITUÍVEL pela carga oficial sem mudar a lógica.
 *
 * Publicação sem quatro olhos no seed (publishedBy null). Idempotente: reusa as
 * versões vigentes se já existirem e o import faz upsert — re-seed não duplica.
 */
class LouosQuadro11Seeder extends Seeder
{
    public function run(): void
    {
        $this->publicarQuadro(
            RuleDomain::LouosQuadro11,
            'lei-9148-2016-quadro11',
            '11',
            'importacao-quadro11',
            'Quadro 11 da LOUOS (condições pela via — modelado da Lei 9.148/2016)',
        );

        $this->publicarQuadro(
            RuleDomain::LouosQuadro11a,
            'lei-9148-2016-quadro11a',
            '11a',
            'importacao-quadro11a',
            'Quadro 11A da LOUOS (condições complementares pela via — modelado da Lei 9.148/2016)',
        );
    }

    private function publicarQuadro(
        RuleDomain $domain,
        string $versionId,
        string $quadro,
        string $event,
        string $descricao,
    ): void {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente($domain)->first();

        if ($version === null) {
            $draft = $rules->openDraft($domain, $versionId, 'Lei nº 9.148/2016 — '.$descricao);

            $version = $rules->publish($draft);
        }

        $report = app(LouosQuadro11ImportService::class)->import(
            $version,
            database_path('data/louos/quadro11-condicoes-via.csv'),
            $quadro,
        );

        app(AuditService::class)->log(
            logName: 'louos',
            event: $event,
            description: 'Importação do '.$descricao,
            properties: $report,
            rulesVersion: $versionId,
        );
    }
}
