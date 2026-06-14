<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestOrigin;
use App\Enums\ViabilityRequestStatus;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\DocumentRequirement;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    use RefreshDatabase;

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
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Cidadão beneficiário informado pelo operador (referenciado por CPF).
     */
    private function beneficiario(): User
    {
        return User::factory()->cidadao()->create();
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
    private function payload(User $beneficiario, Company $company, Cnae $principal, array $overrides = []): array
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

        $this->actingAs($operador, 'gestao')
            ->from(route('gestao.contingencia.create'))
            ->post(route('gestao.contingencia.store'), $this->payload($beneficiario, $company, $principal))
            ->assertRedirect(route('gestao.contingencia.create'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();

        $this->assertSame(ViabilityRequestOrigin::Contingencia, $solicitacao->origin);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);
        $this->assertMatchesRegularExpression('/^VIA-\d{4}-\d{6}$/', (string) $solicitacao->protocol_number);
        $this->assertNotNull($solicitacao->protocoled_at);
        $this->assertSame($beneficiario->id, $solicitacao->requester_user_id);
        $this->assertSame($operador->id, $solicitacao->created_by_user_id);
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
            'requester_user_id' => $beneficiario->id,
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
            ->assertRedirect(route('gestao.contingencia.create'))
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        $solicitacao = ViabilityRequest::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame(ViabilityRequestStatus::Protocolada, $solicitacao->status);

        $documento = $solicitacao->documents()->where('requirement_id', $requisito->id)->firstOrFail();
        $this->assertNotNull($documento->sha256);
        Storage::disk('local')->assertExists($documento->path);
    }
}
