<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cidadao;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Registrar solicitação em CONTINGÊNCIA (HU-148) — o canal de operador na
 * retaguarda e o caminho REAL de operação enquanto o contrato Regin não chega
 * (Fase 13). O operador autorizado (permissão registrar-contingencia) preenche
 * o MESMO conjunto de dados do formulário oficial e o sistema protocola pelo
 * MESMO motor do canal normal (ProtocolarSolicitacaoService, 08-10) — nunca um
 * atalho decisório (RN-002): muda só a origem (`contingencia`, auditada — RN-003)
 * e o ator (operador). Motivo obrigatório (RN-001); referência externa (BAP/Regin)
 * vinculável sem duplicar processo (RN-004 — reusa a detecção de duplicidade do
 * 08-05). Sem a permissão, 403 auditado (CA-04).
 */
class ContingenciaTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Operador autorizado (gestor tem registrar-contingencia + acessar-gestao).
     */
    private function operador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cidadão beneficiário informado pelo operador (referenciado por CPF).
     */
    private function beneficiario(): Cidadao
    {
        return Cidadao::factory()->create();
    }

    /**
     * Polígono de 4 pontos (quadrilátero fechado) em Salvador — fonte portável.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    private function poligono(): array
    {
        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [-38.5108, -12.9711],
                [-38.5108, -12.9709],
                [-38.5106, -12.9709],
                [-38.5106, -12.9711],
                [-38.5108, -12.9711],
            ]],
        ];
    }

    /**
     * Conjunto de dados do formulário oficial para um registro em contingência
     * (beneficiário, empresa, imóvel/polígono, área, atividade principal e
     * motivo obrigatório).
     *
     * @return array<string, mixed>
     */
    private function payload(Cidadao $beneficiario, Company $company, Cnae $principal, array $overrides = []): array
    {
        return array_merge([
            'beneficiary_cpf' => $beneficiario->cpf,
            'company_cnpj' => $company->cnpj,
            'contingency_reason' => 'Integrador Regin indisponível — atendimento presencial do requerente.',
            'used_area_m2' => 120.0,
            'address_reference' => 'Em frente à praça.',
            'property_polygon_geojson' => $this->poligono(),
            'principal_cnae_id' => $principal->id,
        ], $overrides);
    }

    public function test_registra_em_contingencia_segue_fluxo_padrao(): void
    {
        // CA-01: operador autorizado registra na retaguarda com origem
        // 'contingencia' e o sistema PROTOCOLA pelo MESMO serviço/motor —
        // status protocolada, número gerado, transição/auditoria, motivo salvo.
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $resposta = $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal));

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();

        $resposta
            ->assertRedirect(route('gestao.processos.show', $solicitacao))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $this->assertSame(ViabilityRequestOrigin::Contingencia, $solicitacao->origin);
        $this->assertNotSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $solicitacao->protocol_number);
        $this->assertNotNull($solicitacao->protocoled_at);
        $this->assertSame($beneficiario->id, $solicitacao->requester_cidadao_id);
        $this->assertSame($operador->id, $solicitacao->created_by_id);
        $this->assertSame(User::class, $solicitacao->created_by_type);
        $this->assertNotNull($solicitacao->contingency_reason);

        // MESMO motor: a transição rascunho→protocolada foi gravada e auditada.
        $this->assertSame(1, $solicitacao->transitions()->where('to_status', ViabilityRequestStatus::Protocolada)->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'solicitacoes',
            'event' => 'transicao',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'causer_id' => $operador->id,
        ]);
    }

    public function test_requerente_e_operador_com_o_mesmo_id_ficam_cada_um_no_seu_tipo(): void
    {
        $operador = User::factory()->administrador()->withAcceptedLgpdTerm()->create(['id' => 7001]);
        $beneficiario = Cidadao::factory()->create(['id' => 7001]);
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $this->actingAs($operador, 'gestao')
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal))
            ->assertSessionHasNoErrors();

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame($beneficiario->id, $solicitacao->requester_cidadao_id);
        $this->assertSame(Cidadao::class, $solicitacao->requester->getMorphClass());
        $this->assertSame(User::class, $solicitacao->created_by_type);
        $this->assertSame($operador->id, $solicitacao->created_by_id);
        $this->assertSame($beneficiario->cpf, $solicitacao->requester->cpf);
    }

    public function test_cpf_sem_cadastro_vira_registro_de_balcao_com_o_nome_da_sedur(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        Http::fake(['*/pessoafisica/cpf/*' => Http::response(['nome' => 'Joana do Balcão'])]);

        $operador = $this->operador();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal);

        $this->actingAs($operador, 'gestao')
            ->post(route('gestao.contingencia.store'), $dados)
            ->assertSessionHasNoErrors();

        $balcao = Cidadao::query()->where('cpf', '52998224725')->firstOrFail();
        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame('Joana do Balcão', $balcao->name);
        $this->assertFalse($balcao->temSenha());
        $this->assertNull($balcao->email_verified_at);
        $this->assertSame($balcao->id, $solicitacao->requester_cidadao_id);
        $this->assertSame(0, User::query()->where('cpf', '52998224725')->count());
    }

    public function test_cpf_sem_cadastro_e_fora_da_sedur_bloqueia_sem_criar(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        Http::fake(['*/pessoafisica/cpf/*' => Http::response([], 404)]);

        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal);

        $this->actingAs($this->operador(), 'gestao')
            ->post(route('gestao.contingencia.store'), $dados)
            ->assertSessionHasErrors('beneficiary_cpf');

        $this->assertSame(0, Cidadao::query()->count());
        $this->assertSame(0, ViabilityRequest::query()->count());
    }

    public function test_grava_tipo_imovel_inscricao_e_submete_ao_motor(): void
    {
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $resposta = $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal, [
                'tipo_imovel' => 'Galpão',
                'property_registration' => '0010010010',
                'is_virtual_office' => true,
            ]));

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();

        $resposta->assertRedirect(route('gestao.processos.show', $solicitacao));
        $this->assertSame('Galpão', $solicitacao->tipo_imovel);
        $this->assertSame('0010010010', $solicitacao->property_registration);
        $this->assertTrue($solicitacao->wants_virtual_office_hq);
        $this->assertNotSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);
        $this->assertNotSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
    }

    public function test_motivo_obrigatorio(): void
    {
        // RN-001: motivo da contingência é obrigatório.
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal, [
                'contingency_reason' => '',
            ]))
            ->assertSessionHasErrors('contingency_reason');

        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_exige_permissao(): void
    {
        // CA-04: sem registrar-contingencia → 403 auditado (analista acessa a
        // gestão mas não tem a permissão do canal de contingência).
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->post(route('gestao.contingencia.store'), [])
            ->assertForbidden();

        $this->actingAs($analista, 'gestao')
            ->get(route('gestao.contingencia.create'))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'result' => 'bloqueado',
            'causer_id' => $analista->id,
        ]);

        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_origem_visivel(): void
    {
        // RN-003: a origem 'contingencia' fica persistida (dimensão visível no
        // processo e nos relatórios).
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('viability_requests', [
            'company_id' => $company->id,
            'origin' => ViabilityRequestOrigin::Contingencia->value,
        ]);

        // A criação também é auditada com o operador como autor (RN-002).
        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertDatabaseHas('activity_log', [
            'event' => 'created',
            'subject_type' => $solicitacao->getMorphClass(),
            'subject_id' => $solicitacao->id,
            'causer_id' => $operador->id,
        ]);
    }

    public function test_vinculo_externo_nao_duplica(): void
    {
        // RN-004: referência externa (BAP/Regin) repetida NÃO cria processo
        // duplicado — reusa a detecção de duplicidade do 08-05.
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        // Processo já registrado com a mesma referência externa.
        $existente = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $company->id,
            'requester_cidadao_id' => $beneficiario->id,
            'external_reference' => 'REGIN-2026-0001',
        ]);

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal, [
                'external_reference' => 'REGIN-2026-0001',
            ]))
            ->assertRedirect(route('gestao.contingencia.create'))
            ->assertSessionHas('error');

        // Não duplicou: continua existindo apenas o processo anterior.
        $this->assertSame(
            1,
            ViabilityRequest::query()->where('external_reference', 'REGIN-2026-0001')->count(),
        );
        $this->assertDatabaseCount('viability_requests', 1);
        $this->assertTrue(ViabilityRequest::query()->whereKey($existente->id)->exists());
    }

    public function test_mesmo_motor_bloqueio_documental_se_aplica(): void
    {
        // RN-002: a contingência usa o MESMO motor (ProtocolarSolicitacaoService),
        // sem atalho — o bloqueio documental (HU-067) também se aplica. Sem o
        // documento obrigatório, nada é protocolado e nada fica meio-criado.
        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $requisito = DocumentRequirement::factory()->required()->create();
        $requisito->cnaes()->attach($principal->id);

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal))
            ->assertRedirect(route('gestao.contingencia.create'))
            ->assertSessionHas('error');

        $this->assertStringContainsString($requisito->name, (string) session('error'));

        // Bloqueio anti-fachada: o processo NÃO é criado nem protocolado.
        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_protocolo_bloqueado_desfaz_o_registro_de_balcao(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        Http::fake(['*/pessoafisica/cpf/*' => Http::response(['nome' => 'Joana do Balcão'])]);
        Notification::fake();

        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        DocumentRequirement::factory()->required()->create()->cnaes()->attach($principal->id);
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal);

        $this->actingAs($this->operador(), 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $dados)
            ->assertSessionHas('error');

        $this->assertDatabaseCount('viability_requests', 0);
        $this->assertSame(0, Cidadao::query()->count());
        Notification::assertNothingSent();
    }

    public function test_sedur_indisponivel_bloqueia_sem_criar_com_mensagem_propria(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        Http::fake(['*/pessoafisica/cpf/*' => Http::response([], 500)]);

        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal);

        $this->actingAs($this->operador(), 'gestao')
            ->post(route('gestao.contingencia.store'), $dados)
            ->assertSessionHasErrors(['beneficiary_cpf' => 'CPF sem cadastro no Viabiliza e a consulta da SEDUR está indisponível. Tente novamente.']);

        $this->assertSame(0, Cidadao::query()->count());
        $this->assertDatabaseCount('viability_requests', 0);
    }

    public function test_consulta_na_sedur_e_auditada_com_dado_pessoal_em_sucesso_nao_encontrado_e_indisponivel(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        $operador = $this->operador();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal);

        foreach ([
            ['resposta' => Http::response([], 404), 'resultado' => 'falha'],
            ['resposta' => Http::response([], 500), 'resultado' => 'falha'],
            ['resposta' => Http::response(['nome' => 'Joana']), 'resultado' => 'sucesso'],
        ] as $caso) {
            Http::swap(new Factory);
            Http::fake(['*/pessoafisica/cpf/*' => $caso['resposta']]);

            $this->actingAs($operador, 'gestao')->post(route('gestao.contingencia.store'), $dados);

            $this->assertDatabaseHas('activity_log', [
                'log_name' => 'contingencia',
                'event' => 'consulta-cpf',
                'result' => $caso['resultado'],
                'personal_data' => true,
            ]);
        }

        $this->assertSame(2, DB::table('activity_log')->where('log_name', 'contingencia')->where('event', 'consulta-cpf')->where('result', 'falha')->count());
    }

    public function test_outro_campo_invalido_nao_consulta_a_sedur(): void
    {
        config(['viabiliza.integrations.sedur_contato.base_url' => 'https://sedur.test/k8s/prd/ws-contato']);
        Http::fake();

        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();
        $dados = $this->payload(Cidadao::factory()->make(['cpf' => '52998224725']), $company, $principal, ['contingency_reason' => '']);

        $this->actingAs($this->operador(), 'gestao')
            ->post(route('gestao.contingencia.store'), $dados)
            ->assertSessionHasErrors('contingency_reason');

        Http::assertNothingSent();
    }

    public function test_anexo_satisfaz_requisito_e_protocola(): void
    {
        // Anti-fachada (ponta a ponta): com o documento obrigatório anexado, o
        // MESMO motor protocola de verdade — o anexo é gravado e a solicitação
        // nasce protocolada em contingência.
        Storage::fake('local');

        $operador = $this->operador();
        $beneficiario = $this->beneficiario();
        $company = Company::factory()->create();
        $principal = Cnae::factory()->create();

        $requisito = DocumentRequirement::factory()->required()->create();
        $requisito->cnaes()->attach($principal->id);

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal, [
                'documents' => [
                    $requisito->id => UploadedFile::fake()->create('contrato.pdf', 120, 'application/pdf'),
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertNotSame(ViabilityRequestStatus::Rascunho, $solicitacao->status);

        $documento = $solicitacao->documents()->where('requirement_id', $requisito->id)->firstOrFail();
        $this->assertNotNull($documento->sha256);
        $this->assertSame(User::class, $documento->uploaded_by_type);
        $this->assertSame($operador->id, $documento->uploaded_by_id);
        Storage::disk('local')->assertExists($documento->path);
    }

    public function test_operador_acessa_tela_de_contingencia(): void
    {
        // A tela do console existe e recebe os insumos do formulário oficial
        // (tipos de serviço, requisitos documentais e configuração do mapa).
        $operador = $this->operador();

        $this->actingAs($operador, 'gestao')
            ->get(route('gestao.contingencia.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/contingencia/create')
                ->has('serviceTypes')
                ->has('documentRequirements')
                ->has('tiposImovel')
                ->has('tiposImovel.0.value')
                ->has('mapa'));
    }
}
