<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\AnalysisRecordStatus;
use App\Enums\RuleDomain;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\VirtualOfficeInscriptionLock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Ficha de análise EV (T02): a ficha expõe um bloco `escritorioVirtual` com o
 * flag do gatilho (RN-EV-01 — CNAE 8211-3/00 + requerente "quero ser sede"),
 * a inscrição e o painel de ABRIGADOS da inscrição quando a solicitação é a
 * SEDE ativa (RN-EV-03/05). O painel reusa o recorte do relatório R1
 * (RelatorioSedeEscritorioVirtualService); a VALIDADE do produto não é modelada
 * (desfecho spec-2) e degrada para null → "—" na tela, jamais inventada.
 *
 * O autocomplete de condicionantes serve os itens do cadastro VERSIONADO
 * VIGENTE (RuleVersion vigente do domínio sanitário — a "tela de Condicionantes"
 * da gestão), gated por analisar-processos.
 */
class FichaEvPainelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cria a SEDE ativa (lock) da inscrição informada com o CNAE gatilho e a
     * resposta do requerente "quero ser sede = Sim", já com a ficha marcando
     * is_virtual_office_hq. Devolve a solicitação-sede.
     */
    private function criarSedeAtiva(string $inscricao, string $protocolo, string $tvlSede): ViabilityRequest
    {
        $sedeCompany = Company::factory()->create([
            'trade_name' => 'Sede EV Ltda',
            'legal_name' => 'Sede Escritório Virtual Ltda',
        ]);

        $sede = ViabilityRequest::factory()->protocoled()->create([
            'property_registration' => $inscricao,
            'protocol_number' => $protocolo,
            'company_id' => $sedeCompany->id,
            'wants_virtual_office_hq' => true,
        ]);

        $cnaeGatilho = Cnae::factory()->create(['code' => '8211300']);
        $sede->cnaes()->attach($cnaeGatilho->id, ['is_primary' => true]);

        ViabilityDecision::factory()->create([
            'viability_request_id' => $sede->id,
            'is_virtual_office_hq' => true,
            'tvl_product_number' => $tvlSede,
        ]);

        VirtualOfficeInscriptionLock::create([
            'property_registration' => $inscricao,
            'sede_viability_request_id' => $sede->id,
            'active' => true,
            'locked_at' => now(),
        ]);

        AnalysisRecord::factory()->create([
            'viability_request_id' => $sede->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
            'is_virtual_office_hq' => true,
            'per_cnae' => [[
                'cnae' => '8211300',
                'cnae_formatado' => '8211-3/00',
                'is_primary' => true,
                'status_sugerido' => 'analise',
                'status_escolhido' => 'analise',
            ]],
            'parecer' => null,
        ]);

        return $sede;
    }

    public function test_ficha_da_sede_expoe_gatilho_e_painel_de_abrigados(): void
    {
        $sede = $this->criarSedeAtiva('INSC-EV-1', 'VIA-EV-SEDE1', 'TVL-EV-SEDE1');

        // Dois abrigados da MESMA inscrição, cada um com empresa e TVL próprios.
        foreach (['AB1', 'AB2'] as $sufixo) {
            $company = Company::factory()->create(['legal_name' => "Abrigado {$sufixo} ME"]);
            $abrigado = ViabilityRequest::factory()->protocoled()->create([
                'property_registration' => 'INSC-EV-1',
                'protocol_number' => "VIA-EV-{$sufixo}",
                'company_id' => $company->id,
            ]);
            ViabilityDecision::factory()->create([
                'viability_request_id' => $abrigado->id,
                'is_virtual_office_tenant' => true,
                'virtual_office_hq_tvl_number' => 'TVL-EV-SEDE1',
                'tvl_product_number' => "TVL-EV-{$sufixo}",
            ]);
        }

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$sede->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/ficha-analise/show')
                ->where('escritorioVirtual.gatilho', true)
                ->where('escritorioVirtual.is_sede', true)
                ->where('escritorioVirtual.inscricao', 'INSC-EV-1')
                ->has('escritorioVirtual.abrigados', 2)
                ->has('escritorioVirtual.abrigados.0', fn (Assert $linha) => $linha
                    ->hasAll(['tvl', 'razao_social', 'validade'])
                    ->where('validade', null)
                    ->etc()));
    }

    public function test_sede_sem_abrigados_tem_painel_vazio(): void
    {
        // CA-F-03: sede ativa, porém nenhuma inscrição abrigada ainda → painel vazio.
        $sede = $this->criarSedeAtiva('INSC-EV-2', 'VIA-EV-SEDE2', 'TVL-EV-SEDE2');

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$sede->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('escritorioVirtual.is_sede', true)
                ->has('escritorioVirtual.abrigados', 0));
    }

    public function test_processo_comum_nao_marca_sede_nem_gatilho(): void
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'wants_virtual_office_hq' => false,
        ]);
        AnalysisRecord::factory()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'status' => AnalysisRecordStatus::Rascunho,
        ]);

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$request->id}/ficha")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('escritorioVirtual.gatilho', false)
                ->where('escritorioVirtual.is_sede', false)
                ->has('escritorioVirtual.abrigados', 0));
    }

    public function test_autocomplete_condicionantes_retorna_itens_do_cadastro_vigente(): void
    {
        // Versão sanitária VIGENTE com uma condicionante.
        $vigente = RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'sanitario-ev-vigente',
            'rules_version' => 'sanitario-ev-vigente',
        ]);
        RiskCondicionante::factory()->create([
            'rule_version_id' => $vigente->id,
            'cnae_code' => '8211300',
            'pergunta' => 'Possui AVCB do corpo de bombeiros?',
            'texto_parecer' => 'Apresentar AVCB válido do corpo de bombeiros.',
        ]);

        // Versão SUBSTITUÍDA (fora de vigência): seus itens NÃO podem aparecer.
        $antiga = RuleVersion::factory()->substituida()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'sanitario-ev-antiga',
            'rules_version' => 'sanitario-ev-antiga',
        ]);
        RiskCondicionante::factory()->create([
            'rule_version_id' => $antiga->id,
            'cnae_code' => '8211300',
            'pergunta' => 'Condicionante antiga fora de vigência?',
            'texto_parecer' => 'Texto de condicionante ANTIGA que não deve aparecer.',
        ]);

        $response = $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/condicionantes/autocomplete?q=AVCB')
            ->assertOk();

        $labels = collect($response->json('data'))->pluck('label')->all();
        $this->assertContains('Apresentar AVCB válido do corpo de bombeiros.', $labels);
        $this->assertNotContains('Texto de condicionante ANTIGA que não deve aparecer.', $labels);
    }

    public function test_autocomplete_condicionantes_exige_analisar_processos(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->getJson('/gestao/condicionantes/autocomplete?q=x')
            ->assertForbidden();
    }
}
