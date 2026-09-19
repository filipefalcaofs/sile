<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegra;
use App\Services\Rules\RuleVersionService;
use App\Services\Tratamento\TratamentoRegrasImportService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

class TratamentoRegrasSeeder extends Seeder
{
    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($version === null) {
            $draft = $rules->openDraft(
                RuleDomain::RiscoTratamento,
                'planilha-20-08-26',
                'Planilha de regras de tratamento 20.08.26',
            );

            $version = $rules->publish($draft);
        }

        TratamentoPergunta::query()->where('rule_version_id', $version->getKey())->delete();
        TratamentoRegra::query()->where('rule_version_id', $version->getKey())->delete();
        TratamentoEnquadramento::query()->where('rule_version_id', $version->getKey())->delete();
        TratamentoCnaeBinding::query()->where('rule_version_id', $version->getKey())->delete();

        $report = app(TratamentoRegrasImportService::class)->import(
            $version,
            database_path('data/regras-20-08-26'),
        );

        app(AuditService::class)->log(
            logName: 'tratamento',
            event: 'importacao-planilha',
            description: 'Carga da planilha 20.08.26',
            subject: $version,
            properties: $report,
            result: 'sucesso',
            rulesVersion: $version->version,
        );
    }
}
