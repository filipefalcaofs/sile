<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Busca global da retaguarda (HU-082 RN-009 — atalho Cmd/Ctrl+K): um endpoint
 * LEVE que casa o termo contra número do processo, BAP, produto TVL, CNPJ e nome
 * (empresa/requerente) com whereLike caseSensitive:false e devolve poucos
 * resultados em JSON ({id, protocolo, empresa, link}) para levar direto ao
 * processo — sem passar pela tela de filtros. Gated por consultar-solicitacoes;
 * o 403 é auditado no ponto único.
 */
class ProcessoBuscaGlobalTest extends TestCase
{
    use RefreshDatabase;

    private int $seq = 0;

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
     * @param  array<string, mixed>  $attrs
     */
    private function processo(array $attrs = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attrs));
    }

    public function test_sem_permissao_recebe_403_auditado(): void
    {
        $semPermissao = User::factory()->withAcceptedLgpdTerm()->create();
        $semPermissao->givePermissionTo('acessar-gestao');

        $this->actingAs($semPermissao, 'gestao')
            ->getJson('/gestao/processos/busca?q=VIA')
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'seguranca',
            'event' => 'acesso-negado',
            'result' => 'bloqueado',
        ]);
    }

    public function test_busca_por_protocolo_case_insensitive(): void
    {
        $alvo = $this->processo(['protocol_number' => 'VIA-'.now()->year.'-007777']);
        $this->processo(['protocol_number' => 'VIA-'.now()->year.'-008888']);

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/busca?q=via-'.now()->year.'-007777')
            ->assertOk()
            ->assertJsonCount(1, 'resultados')
            ->assertJsonPath('resultados.0.id', $alvo->id)
            ->assertJsonPath('resultados.0.link', route('gestao.processos.show', $alvo));
    }

    public function test_busca_por_cnpj_da_empresa(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '99888777000166']);
        $alvo = $this->processo(['company_id' => $empresa->id]);
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/busca?q=99888777')
            ->assertOk()
            ->assertJsonCount(1, 'resultados')
            ->assertJsonPath('resultados.0.id', $alvo->id);
    }

    public function test_busca_por_nome_da_empresa(): void
    {
        $empresa = Company::factory()->create(['legal_name' => 'Padaria Pão Quente LTDA', 'trade_name' => 'Pão Quente']);
        $alvo = $this->processo(['company_id' => $empresa->id]);
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/busca?q=pão quente')
            ->assertOk()
            ->assertJsonCount(1, 'resultados')
            ->assertJsonPath('resultados.0.id', $alvo->id);
    }

    public function test_q_vazio_retorna_lista_vazia(): void
    {
        $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->getJson('/gestao/processos/busca?q=')
            ->assertOk()
            ->assertJsonCount(0, 'resultados');
    }
}
