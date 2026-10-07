<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Tratamento\PlanilhaTratamentoImporter;
use App\Services\Tratamento\TratamentoRegrasImportService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A planilha de enquadramento virou aba do hub Motor de regras
 * (/gestao/louos?aba=enquadramento). A rota antiga (/gestao/regras-tratamento)
 * redireciona para a aba — links existentes não quebram.
 */
class TratamentoRegrasUiTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_rota_antiga_redireciona_para_a_aba_do_hub(): void
    {
        $this->actingAs(User::factory()->administrador()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/regras-tratamento')
            ->assertRedirect('/gestao/louos?aba=enquadramento');
    }

    public function test_aba_enquadramento_mostra_planilha_vigente(): void
    {
        $versao = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoTratamento,
            'version' => 'planilha-20-08-26',
        ]);
        (new TratamentoRegrasImportService)->import($versao, database_path('data/regras-20-08-26'));
        app(PlanilhaTratamentoImporter::class)->complementar($versao, database_path('data/regras-20-08-26/planilha-regras.xlsx'));

        $this->actingAs(User::factory()->administrador()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/louos?aba=enquadramento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('aba', 'enquadramento')
                ->where('enquadramento.versao', 'planilha-20-08-26')
                ->where('enquadramento.contagens.perguntas', 32)
                ->where('enquadramento.contagens.regras', 59)
                ->where('enquadramento.contagens.condicionantes', 32)
                ->has('enquadramento.perguntas', 32)
                ->where('enquadramento.perguntas', fn (Collection $perguntas): bool => array_is_list($perguntas->all()))
                ->where('enquadramento.regras', fn (Collection $regras): bool => array_is_list($regras->all()))
                ->where('enquadramento.ramos', fn (Collection $ramos): bool => array_is_list($ramos->all()))
                ->has('enquadramento.pendencias', 12)
                ->where('enquadramento.pendencias.0.regra', 29)
                ->where('enquadramento.pendencias', fn (Collection $pendencias): bool => array_is_list($pendencias->all()))
                ->has('enquadramento.cnlu', 15)
                ->where('enquadramento.enquadramentos.data', fn (Collection $linhas): bool => array_is_list($linhas->all()))
                ->has('enquadramento.regras', 59)
                ->has('enquadramento.ramos')
                ->has('enquadramento.condicionantes', 32)
                ->has('enquadramento.enquadramentos.data')
                ->where('enquadramento.enquadramentos.data', function (Collection $linhas): bool {
                    $linha = $linhas->first(
                        fn (array $item): bool => $item['cnae'] === '0111-3/01' && $item['codigo_louos'] === '07.12.13',
                    );

                    return is_array($linha)
                        && $linha['denominacao'] === 'Cultivo de arroz'
                        && $linha['enquadramento2'] === 'nR2-12'
                        && abs((float) $linha['ate_m2'] - 1250) < 0.001
                        && abs((float) $linha['ate_m2_2'] - 1250.01) < 0.001;
                }));
    }

    public function test_aba_enquadramento_sem_versao_nao_inventa_planilha(): void
    {
        $this->actingAs(User::factory()->administrador()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/louos?aba=enquadramento')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/index')
                ->where('enquadramento.versao', null)
                ->where('enquadramento.contagens.cnaes', 0));
    }
}
