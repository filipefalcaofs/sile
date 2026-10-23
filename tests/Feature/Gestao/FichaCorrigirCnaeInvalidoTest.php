<?php

namespace Tests\Feature\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Alteração de atividade com código CNAE fora da lista oficial: a ficha mostra o
 * que entra, o que sai e o que já existia, e o analista troca o código inválido
 * por um oficial. A comparação é refeita sem nova consulta à SEFAZ.
 */
class FichaCorrigirCnaeInvalidoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['viabiliza.sefaz.consulta.ativa' => true]);

        Cnae::factory()->create(['code' => '4711302', 'description' => 'Supermercados']);
        Cnae::factory()->create(['code' => '5611201', 'description' => 'Restaurantes']);
        Cnae::factory()->create(['code' => '4712100', 'description' => 'Minimercados']);
    }

    private function analista(bool $comPermissao = true): User
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $usuario->givePermissionTo($comPermissao ? ['acessar-gestao', 'analisar-processos'] : ['acessar-gestao']);

        return $usuario;
    }

    private function processo(): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => Company::factory()->create(['cnpj' => '12345678000190'])->id,
        ]);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'analysis_status' => AnalysisStatus::EmAnalise])->save();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $processo->id, 'codigo' => '244', 'nome' => 'Alteração']);

        $invalido = Cnae::factory()->create(['code' => '5611999', 'section_description' => 'REGIN', 'description' => 'CNAE 5611999 (REGIN)']);
        $processo->cnaes()->attach(Cnae::query()->where('code', '4711302')->first()->id, ['is_primary' => true]);
        $processo->cnaes()->attach($invalido->id, ['is_primary' => false]);

        $processo->forceFill(['comparacao_cnaes' => [
            'incluidas' => [],
            'excluidas' => ['4712100'],
            'mantidas' => ['4711302'],
            'invalidas' => ['5611999'],
            'numero_viabilidade' => 'VIA-2026-77',
            'sem_viabilidade' => false,
        ]])->save();

        return $processo->fresh();
    }

    public function test_ficha_lista_incluidas_excluidas_mantidas_e_invalidas_com_descricao(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('comparacaoCnaes.numero_viabilidade', 'VIA-2026-77')
                ->where('comparacaoCnaes.excluidas.0.codigo', '4712-1/00')
                ->where('comparacaoCnaes.excluidas.0.descricao', 'Minimercados')
                ->where('comparacaoCnaes.mantidas.0.descricao', 'Supermercados')
                ->where('comparacaoCnaes.invalidas.0.codigo', '5611-9/99'));
    }

    public function test_ficha_de_pedido_sem_comparacao_nao_traz_o_bloco(): void
    {
        $processo = $this->processo();
        $processo->forceFill(['comparacao_cnaes' => null])->save();

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$processo->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('comparacaoCnaes', null));
    }

    public function test_analista_corrige_o_codigo_invalido_e_a_comparacao_e_refeita(): void
    {
        $processo = $this->processo();
        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/cnae-invalido", ['codigo_atual' => '5611999', 'codigo_novo' => '5611201'])
            ->assertOk();

        $processo->refresh();
        $this->assertSame(['4711302', '5611201'], $processo->cnaes()->orderBy('code')->pluck('code')->all());
        $this->assertSame(['5611201'], $processo->comparacao_cnaes['incluidas']);
        $this->assertSame([], $processo->comparacao_cnaes['invalidas']);
        $this->assertSame(['4712100'], $processo->comparacao_cnaes['excluidas']);

        $log = Activity::query()->where('event', 'cnae-invalido-corrigido')->sole();
        $this->assertSame($analista->id, $log->causer_id);
        $this->assertSame('5611999', $log->properties['codigo_atual']);
        $this->assertSame('5611201', $log->properties['codigo_novo']);
    }

    public function test_a_ficha_recalculada_leva_o_codigo_corrigido_para_o_produto(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/cnae-invalido", ['codigo_atual' => '5611-9/99', 'codigo_novo' => '5611-2/01'])
            ->assertOk();

        $ficha = AnalysisRecord::query()->where('viability_request_id', $processo->id)->orderByDesc('revision')->first();
        $codigos = array_column($ficha->per_cnae ?? [], 'cnae');

        $this->assertContains('5611201', $codigos);
        $this->assertNotContains('5611999', $codigos);
    }

    public function test_codigo_corrigido_ainda_invalido_e_recusado(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/cnae-invalido", ['codigo_atual' => '5611999', 'codigo_novo' => '9999999'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo_novo');

        $this->assertContains('5611999', $processo->fresh()->cnaes()->pluck('code')->all());
    }

    public function test_so_corrige_codigo_que_a_comparacao_marcou_como_invalido(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(), 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/cnae-invalido", ['codigo_atual' => '4711302', 'codigo_novo' => '5611201'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('codigo_atual');
    }

    public function test_quem_nao_analisa_processos_nao_corrige(): void
    {
        $processo = $this->processo();

        $this->actingAs($this->analista(comPermissao: false), 'gestao')
            ->patchJson("/gestao/processos/{$processo->id}/cnae-invalido", ['codigo_atual' => '5611999', 'codigo_novo' => '5611201'])
            ->assertForbidden();
    }
}
