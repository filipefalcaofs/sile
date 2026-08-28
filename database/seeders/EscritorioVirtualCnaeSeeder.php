<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\VirtualOfficeActivityCnae;
use App\Services\EscritorioVirtual\EscritorioVirtualCnaeImportService;
use App\Services\Rules\RuleVersionService;
use App\Support\Audit\AuditService;
use Illuminate\Database\Seeder;

/**
 * Carga das listas de atividade por anexo do Decreto 35.062/2021 (Anexo A =
 * SEDE, Anexo B = ABRIGADO, RN-EV-05/07): publica uma versão vigente do
 * domínio atividades_escritorio_virtual e importa os dois snapshots CSV,
 * registrando o relatório (contadores) na trilha de auditoria com a versão
 * de regras. Espelha RiscoSanitarioSeeder.
 *
 * Fonte oficial: endpoint SEDUR AtividadesPermitidasEmEscritorioVirtual.php;
 * estes CSVs são o snapshot vigente até o fetch live do endpoint.
 *
 * A versão é nomeada pelo conteúdo ('ev-anexos-2026-08-28'), não reusada da
 * vigente anterior: o conteúdo mudou de uma lista única (5 CNAEs, sem
 * discriminador) para os dois anexos do Decreto (325 linhas, Anexo A/B), o
 * que é outra versão de regra. Reusar a vigente antiga manteria linhas
 * históricas — como o 8211-3/00, CNAE que constitui a sede e não consta de
 * nenhum anexo — respondendo como se ainda vigessem. Publicar uma versão
 * nova fecha a antiga (RuleVersionService::publish) e permitidoNoAnexo()
 * passa a filtrar só pela vigente atual.
 *
 * Idempotente: procura a versão pelo nome; se já existir e estiver vigente,
 * não republica. O import faz upsert — re-seed não duplica linhas.
 */
class EscritorioVirtualCnaeSeeder extends Seeder
{
    private const VERSAO = 'ev-anexos-2026-08-28';

    public function run(): void
    {
        $rules = app(RuleVersionService::class);

        $version = RuleVersion::query()
            ->where('domain', RuleDomain::AtividadesEscritorioVirtual->value)
            ->where('version', self::VERSAO)
            ->first();

        if ($version === null) {
            $version = $rules->openDraft(
                RuleDomain::AtividadesEscritorioVirtual,
                self::VERSAO,
                'Anexos A e B do Decreto 35.062/2021 (atividades permitidas em escritório virtual — sede e abrigado)',
            );
        }

        if ($version->status !== RuleVersionStatus::Vigente) {
            $version = $rules->publish($version);
        }

        $relatorioB = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-b-abrigado.csv'),
            VirtualOfficeActivityCnae::ANEXO_B,
        );

        $relatorioA = app(EscritorioVirtualCnaeImportService::class)->import(
            $version,
            database_path('data/escritorio-virtual/anexo-a-sede.csv'),
            VirtualOfficeActivityCnae::ANEXO_A,
        );

        app(AuditService::class)->log(
            logName: 'escritorio-virtual',
            event: 'importacao-lista-ev',
            description: 'Importação das listas de atividade por anexo (escritório virtual)',
            properties: [
                'anexo_a' => $relatorioA['importados'],
                'anexo_b' => $relatorioB['importados'],
            ],
            rulesVersion: self::VERSAO,
        );
    }
}
