<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStage;
use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\DistribuicaoInteligenteService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DistribuicaoInteligenteTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_sugere_para_quem_tem_menos_carga_em_vez_de_dividir_igual(): void
    {
        $setor = 1;
        $processos = [
            $this->item(1, $setor),
            $this->item(2, $setor),
            $this->item(3, $setor),
        ];
        $analistas = [
            $this->analista(10, 'Adriana', 3, [$setor]),
            $this->analista(20, 'Barbara', 15, [$setor]),
        ];

        $sugestao = app(DistribuicaoInteligenteService::class)->sugerir($processos, $analistas);

        $adriana = collect($sugestao['alocacoes'])->firstWhere('analista_id', 10);
        $barbara = collect($sugestao['alocacoes'])->firstWhere('analista_id', 20);

        $this->assertSame(3, $adriana['recebera']);
        $this->assertSame(6, $adriana['apos']);
        $this->assertSame(0, $barbara['recebera']);
        $this->assertSame(15, $barbara['apos']);
        $this->assertSame([], $sugestao['nao_alocados']);
        $this->assertSame(DistribuicaoInteligenteService::CRITERIO, $sugestao['criterio']);
    }

    public function test_empate_de_carga_escolhe_o_nome_em_ordem_alfabetica(): void
    {
        $processos = [$this->item(1, 1)];
        $analistas = [
            $this->analista(2, 'Bruno', 5, [1]),
            $this->analista(1, 'Ana', 5, [1]),
        ];

        $sugestao = app(DistribuicaoInteligenteService::class)->sugerir($processos, $analistas);
        $ana = collect($sugestao['alocacoes'])->firstWhere('analista_id', 1);

        $this->assertSame(1, $ana['recebera']);
    }

    public function test_processo_em_vistoria_so_vai_para_vistoriador(): void
    {
        $processos = [$this->item(1, 1, 'vistoriar')];
        $analistas = [
            $this->analista(1, 'Ana', 0, [1], false),
            $this->analista(2, 'Bia', 8, [1], true),
        ];

        $sugestao = app(DistribuicaoInteligenteService::class)->sugerir($processos, $analistas);
        $bia = collect($sugestao['alocacoes'])->firstWhere('analista_id', 2);

        $this->assertSame(1, $bia['recebera']);
        $this->assertSame([], $sugestao['nao_alocados']);
    }

    public function test_pagina_mostra_a_proposta_sem_tramitar(): void
    {
        $setor = Sector::factory()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);
        $leve = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Ana Leve']);
        $leve->sectors()->attach($setor);
        $cheia = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Bruno Cheio']);
        $cheia->sectors()->attach($setor);

        $this->processoNaCaixa($setor, $cheia);
        $livre = $this->processoNaCaixa($setor);

        $props = $this->actingAs($apoio, 'gestao')
            ->get('/gestao/caixa-setor/distribuicao-inteligente?ids='.$livre->id)
            ->assertOk()
            ->viewData('page')['props'];

        $ana = collect($props['alocacoes'])->firstWhere('analista_id', $leve->id);

        $this->assertSame(1, $ana['recebera']);
        $this->assertNull($livre->fresh()->assigned_user_id);
        $this->assertNotEmpty($props['criterio']);
    }

    public function test_confirmacao_inteligente_audita_a_proposta_e_tramita(): void
    {
        $setor = Sector::factory()->create();
        $apoio = User::factory()->apoio()->withAcceptedLgpdTerm()->create();
        $apoio->sectors()->attach($setor);
        $analista = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $analista->sectors()->attach($setor);
        $processo = $this->processoNaCaixa($setor);

        $this->actingAs($apoio, 'gestao')
            ->post('/gestao/caixa-setor/distribuir-lote', [
                'alocacoes' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$processo->id]],
                ],
                'origem' => 'inteligente',
                'proposta' => [
                    ['analista_id' => $analista->id, 'request_ids' => [$processo->id]],
                ],
                'alteracoes' => [],
            ])
            ->assertRedirect('/gestao/caixa-setor')
            ->assertSessionHas('status');

        $this->assertSame($analista->id, $processo->fresh()->assigned_user_id);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'analise',
            'event' => 'distribuir-inteligente',
        ]);
    }

    /**
     * @param  list<int>  $setores
     * @return array{analista_id: int, analista: string, total: int, pode_vistoria: bool, setores: list<int>}
     */
    private function analista(int $id, string $nome, int $carga, array $setores, bool $vistoria = true): array
    {
        return [
            'analista_id' => $id,
            'analista' => $nome,
            'total' => $carga,
            'pode_vistoria' => $vistoria,
            'setores' => $setores,
        ];
    }

    /**
     * @return array{id: int, sector_id: int, analysis_status: ?string, analysis_due_at: ?string, bap: ?string, protocol_number: ?string}
     */
    private function item(int $id, int $setor, ?string $status = null): array
    {
        return [
            'id' => $id,
            'sector_id' => $setor,
            'analysis_status' => $status,
            'analysis_due_at' => '2026-09-2'.$id,
            'bap' => 'BAP-'.$id,
            'protocol_number' => 'VIA-'.$id,
        ];
    }

    private function processoNaCaixa(Sector $sector, ?User $responsavel = null): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->create();
        $request->forceFill([
            'sector_id' => $sector->id,
            'status' => ViabilityRequestStatus::EmAnalise,
            'analysis_stage' => AnalysisStage::Distribuicao,
            'analysis_status' => AnalysisStatus::ParaDistribuir,
            'analysis_stage_started_at' => now(),
            'analysis_due_at' => now()->addDay(),
            'assigned_user_id' => $responsavel?->id,
            'assigned_at' => $responsavel === null ? null : now(),
        ])->save();

        return $request;
    }
}
