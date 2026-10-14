<?php

namespace App\Console\Commands;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\TratamentoEnquadramento;
use Database\Seeders\TratamentoRegrasSeeder;
use Illuminate\Console\Command;

/**
 * Confere se a planilha de tratamento vigente está completa. Sem o complemento
 * (metadados com a subdivisão dos CNAEs) a pergunta 1 não tem opções para casar
 * com as respostas do REGIN e os processos travam como "pergunta sem resposta".
 */
class TratamentoVerificarCommand extends Command
{
    protected $signature = 'tratamento:verificar
        {--corrigir : Aplica o seeder da planilha (upsert idempotente) antes de conferir}';

    protected $description = 'Confere se a planilha de tratamento vigente está completa (metadados da subdivisão dos CNAEs)';

    public function handle(): int
    {
        if ($this->option('corrigir')) {
            $this->callSilently('db:seed', ['--class' => TratamentoRegrasSeeder::class, '--force' => true]);
        }

        $versao = RuleVersion::vigente(RuleDomain::RiscoTratamento)->first();

        if ($versao === null) {
            $this->error('Sem versão vigente da planilha de tratamento.');

            return self::FAILURE;
        }

        $sem = TratamentoEnquadramento::query()
            ->where('rule_version_id', $versao->getKey())
            ->whereNull('metadados')
            ->count();

        if ($sem > 0) {
            $this->error("Planilha {$versao->version} incompleta: {$sem} enquadramento(s) sem metadados. Rode: php artisan tratamento:verificar --corrigir");

            return self::FAILURE;
        }

        $this->info("Planilha {$versao->version} completa.");

        return self::SUCCESS;
    }
}
