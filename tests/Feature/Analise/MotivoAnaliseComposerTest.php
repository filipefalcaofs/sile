<?php

namespace Tests\Feature\Analise;

use App\Enums\TipoGatilho;
use App\Models\ExpressoQueda;
use App\Models\ViabilityRequest;
use App\Services\Analise\MotivoAnaliseComposer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MotivoAnaliseComposerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_loteamento_itaigara_vira_so_a_frase_operacional(): void
    {
        $request = ViabilityRequest::factory()->create();
        ExpressoQueda::factory()->create([
            'viability_request_id' => $request->id,
            'motivo' => 'Não foi possível verificar a restrição territorial "Loteamento Itaigara (TAC vigente)" (camada geográfica indisponível)',
            'tipo_gatilho' => TipoGatilho::RegraTerritorial,
            'dimensao' => 'territorio',
        ]);

        $linhas = app(MotivoAnaliseComposer::class)->para($request->fresh(), null);

        $this->assertSame(['Solicitação cadastrada no Loteamento Itaigara'], $linhas);
    }

    public function test_cnae_sem_enquadramento_cita_o_codigo_e_a_area(): void
    {
        $request = ViabilityRequest::factory()->create(['used_area_m2' => 156636]);
        ExpressoQueda::factory()->create([
            'viability_request_id' => $request->id,
            'cnae' => '1353900',
            'motivo' => 'Classificação de risco não parametrizada para o CNAE',
            'tipo_gatilho' => TipoGatilho::EnquadramentoAusente,
            'dimensao' => 'municipal',
        ]);

        $linhas = app(MotivoAnaliseComposer::class)->para($request->fresh(), null);

        $this->assertContains('CNAE 1353-9/00 — não enquadrado — para enquadramento do analista', $linhas);
        $this->assertContains('Área utilizada declarada: 156.636 m².', $linhas);
        $this->assertFalse(collect($linhas)->contains(
            fn (string $linha): bool => str_contains($linha, 'gatilho') || str_contains($linha, 'dimensão decisiva'),
        ));
    }
}
