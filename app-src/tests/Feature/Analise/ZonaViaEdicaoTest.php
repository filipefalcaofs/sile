<?php

namespace Tests\Feature\Analise;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\Zona;
use App\Services\Analise\TramitacaoProcessoService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Edição manual da zona e da via na ficha de análise: quem tem a permissão no
 * perfil escolhe entre as zonas e vias da LOUOS, vê o que muda antes de
 * confirmar e deixa tramitação e log de alteração.
 */
class ZonaViaEdicaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        Zona::factory()->create(['codigo' => 'ZCMe', 'nome' => 'Zona de Centralidade Metropolitana', 'ativo' => true]);
        Zona::factory()->create(['codigo' => 'ZPR 3', 'nome' => 'Zona de Proteção', 'ativo' => true]);
    }

    private function editor(): User
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $usuario->givePermissionTo(['acessar-gestao', 'analisar-processos', 'editar-zona-via']);

        return $usuario;
    }

    private function analistaSemPermissao(): User
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $usuario->givePermissionTo(['acessar-gestao', 'analisar-processos']);

        return $usuario;
    }

    private function processo(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'zona_codigo' => 'ZPR 3',
            'camadas_incidentes' => ['via' => 'VC I'],
        ]);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'analysis_status' => AnalysisStatus::EmAnalise])->save();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        return $processo;
    }

    public function test_quem_nao_tem_a_permissao_nao_altera(): void
    {
        $processo = $this->processo();
        $usuario = $this->analistaSemPermissao();
        $dados = ['zona' => 'ZCMe', 'via' => 'VL'];

        $this->actingAs($usuario, 'gestao')->patchJson("/gestao/processos/{$processo->id}/zona-via", $dados)->assertForbidden();

        $this->assertNull($processo->fresh()->zona_manual);
    }

    public function test_zona_e_via_precisam_ser_da_louos(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->editor(), 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/zona-via", ['zona' => 'XYZ', 'via' => 'VX'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['zona', 'via']);
    }

    public function test_alteracao_grava_registra_na_tramitacao_e_no_log(): void
    {
        $processo = $this->processo();
        $editor = $this->editor();

        $this->actingAs($editor, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/zona-via", ['zona' => 'ZCMe', 'via' => 'VL'])
            ->assertOk();

        $processo->refresh();
        $this->assertSame('ZCMe', $processo->zona_manual);
        $this->assertSame('VL', $processo->via_manual);

        $log = Activity::query()->where('event', 'zona-via-alterada')->sole();
        $this->assertSame($editor->id, $log->causer_id);
        $this->assertSame('ZPR 3', $log->properties['antes']['zona']);
        $this->assertSame('VC I', $log->properties['antes']['via']);
        $this->assertSame('ZCMe', $log->properties['depois']['zona']);
        $this->assertSame('VL', $log->properties['depois']['via']);
        $this->assertArrayNotHasKey('justificativa', $log->properties->toArray());
        $this->assertNotNull($log->created_at);

        $eventos = app(TramitacaoProcessoService::class)->eventos($processo);
        $titulos = array_column($eventos, 'to_label');
        $this->assertContains('Zona e via alteradas', $titulos);

        $evento = collect($eventos)->firstWhere('to_label', 'Zona e via alteradas');
        $this->assertStringContainsString('Zona: ZPR 3 → ZCMe', $evento['reason']);
        $this->assertStringContainsString('Via: VC I → VL', $evento['reason']);
        $this->assertStringNotContainsString('Justificativa', $evento['reason']);
    }

    public function test_ficha_mostra_a_zona_e_a_via_efetivas_e_as_opcoes_a_quem_pode_editar(): void
    {
        $processo = $this->processo();
        $processo->forceFill(['zona_manual' => 'ZCMe', 'via_manual' => 'VL'])->save();

        $this->actingAs($this->editor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('localizacao.zona', 'ZCMe')
                ->where('localizacao.via', 'VL')
                ->where('podeEditarZonaVia', true)
                ->where('zonasDisponiveis', fn ($zonas) => collect($zonas)->pluck('codigo')->contains('ZCMe'))
                ->where('viasDisponiveis', fn ($vias) => collect($vias)->contains('VL')));

        $this->actingAs($this->analistaSemPermissao(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('podeEditarZonaVia', false));
    }

    public function test_ficha_informa_que_zona_e_via_foram_alteradas_pelo_analista(): void
    {
        $processo = $this->processo();
        $editor = $this->editor();

        $this->actingAs($editor, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/zona-via", ['zona' => 'ZCMe', 'via' => 'VL'])
            ->assertOk();

        $this->actingAs($editor, 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('localizacao.zona_via_alterada.por', $editor->name)
                ->where('localizacao.zona_via_alterada.antes.zona', 'ZPR 3')
                ->where('localizacao.zona_via_alterada.antes.via', 'VC I')
                ->whereType('localizacao.zona_via_alterada.em', 'string'));
    }

    public function test_ficha_sem_alteracao_manual_nao_traz_o_aviso_de_alteracao(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->editor(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('localizacao.zona_via_alterada', null));
    }
}
