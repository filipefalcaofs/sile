<?php

namespace Tests\Support;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use App\Models\ViabilityRequest;
use App\Services\Tratamento\TratamentoRegrasImportService;

trait SeedsTratamentoPlanilha
{
    protected function seedTratamentoPlanilha(): RuleVersion
    {
        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first()
            ?? RuleVersion::factory()->create([
                'domain' => RuleDomain::RiscoTratamento,
                'version' => 'planilha-20-08-26',
            ]);

        if (TratamentoEnquadramento::query()->where('rule_version_id', $versao->id)->doesntExist()) {
            (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
        }

        $this->reattachRespostasTratamentoNoReload();

        return $versao;
    }

    /**
     * respostasTratamento é efêmero (não persiste). Nos fluxos que recarregam
     * a solicitação (protocolo, job, comando), reanexa P11=sim — o mapa da
     * planilha para o CNAE 4712-1/00 / nR1.
     */
    protected function reattachRespostasTratamentoNoReload(): void
    {
        ViabilityRequest::retrieved(function (ViabilityRequest $request): void {
            if ($request->respostasTratamento === []) {
                $request->respostasTratamento = [11 => true];
            }
        });
    }
}
