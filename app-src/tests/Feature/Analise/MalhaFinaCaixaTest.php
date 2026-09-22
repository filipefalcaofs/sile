<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\MalhaFinaService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Caixa de Malha Fina (GET /gestao/malha-fina): a fila do revisor. Universo
 * = processos com in_fine_mesh (existe encaminhamento aberto — invariante do
 * MalhaFinaService); aba Concluídas = fora da malha fina com baixa registrada.
 * Filtros das caixas (ProcessoQueryService). Gated por analisar-malha-fina;
 * a consulta é auditada (malha-fina-consulta).
 */
class MalhaFinaCaixaTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function revisor(): User
    {
        return User::factory()->gestor()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  array<string, mixed>  $attributos
     */
    private function processo(array $attributos = []): ViabilityRequest
    {
        $protocolo = 'VIA-'.now()->year.'-'.str_pad((string) ++$this->seq, 6, '0', STR_PAD_LEFT);

        return ViabilityRequest::factory()->create(array_merge([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => now(),
        ], $attributos))->fresh();
    }

    private function emMalhaFina(ViabilityRequest $processo, User $ator): ViabilityRequest
    {
        app(MalhaFinaService::class)->encaminhar($processo, $ator, 'Revisão de rotina.');

        return $processo->fresh();
    }

    public function test_caixa_lista_apenas_processos_em_malha_fina(): void
    {
        $revisor = $this->revisor();
        $dentro = $this->emMalhaFina($this->processo(), $revisor);
        $this->processo();

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('gestao/malha-fina/index')
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $dentro->id)
                ->where('processos.data.0.encaminhado_por', $revisor->name)
                ->where('processos.data.0.motivo', 'Revisão de rotina.')
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'malha-fina-consulta',
        ]);
    }

    public function test_filtro_por_protocolo_se_aplica(): void
    {
        $revisor = $this->revisor();
        $alvo = $this->emMalhaFina($this->processo(), $revisor);
        $this->emMalhaFina($this->processo(), $revisor);

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina?protocolo='.$alvo->protocol_number)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $alvo->id)
            );
    }

    public function test_aba_concluidas_lista_quem_saiu_da_malha_fina(): void
    {
        $revisor = $this->revisor();
        $concluido = $this->emMalhaFina($this->processo(), $revisor);
        $this->emMalhaFina($this->processo(), $revisor);

        app(MalhaFinaService::class)->resolverAbertos($concluido->fresh(), $revisor, 'Tudo certo.');

        $this->actingAs($revisor, 'gestao')
            ->get('/gestao/malha-fina?aba=concluidas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('processos.data', 1)
                ->where('processos.data.0.id', $concluido->id)
                ->where('processos.data.0.concluido_por', $revisor->name)
                ->where('processos.data.0.observacao', 'Tudo certo.')
            );
    }

    public function test_sem_permissao_analisar_malha_fina_recebe_403(): void
    {
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/malha-fina')
            ->assertForbidden();
    }
}
