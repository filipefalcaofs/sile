<?php

namespace Tests\Feature\Analise;

use App\Models\Cidadao;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Analise\TramitacaoProcessoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TramitacaoAtorCidadaoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>|null
     */
    private function documentoAnexado(ViabilityRequest $processo): ?array
    {
        $eventos = app(TramitacaoProcessoService::class)->eventos($processo->fresh());

        return collect($eventos)->firstWhere('to_label', 'Documento anexado');
    }

    private function anexadoPor(ViabilityRequest $processo, User|Cidadao $autor): void
    {
        activity('solicitacoes')
            ->performedOn($processo)
            ->causedBy($autor)
            ->event('documento-anexado')
            ->log('Documento anexado à solicitação');
    }

    public function test_cidadao_com_mesmo_id_de_servidor_aparece_pelo_proprio_nome(): void
    {
        $servidor = User::factory()->create(['name' => 'Servidor Do Mesmo Id']);
        Cidadao::factory()->create(['id' => $servidor->id, 'name' => 'Cidada Que Anexou']);
        $processo = ViabilityRequest::factory()->create();

        $this->anexadoPor($processo, Cidadao::query()->findOrFail($servidor->id));

        $item = $this->documentoAnexado($processo);

        $this->assertNotNull($item);
        $this->assertSame('Cidada Que Anexou', $item['ator']);
    }

    public function test_ator_oculto_de_id_coincidente_nao_esconde_a_acao_do_cidadao(): void
    {
        config()->set('viabiliza.tramitacao.atores_ocultos.emails', ['suporte@exemplo.test']);
        $suporte = User::factory()->create(['email' => 'suporte@exemplo.test', 'name' => 'Suporte']);
        Cidadao::factory()->create(['id' => $suporte->id, 'name' => 'Cidadao Do Id Do Suporte']);
        $processo = ViabilityRequest::factory()->create();

        $this->anexadoPor($processo, Cidadao::query()->findOrFail($suporte->id));

        $this->assertSame('Cidadao Do Id Do Suporte', $this->documentoAnexado($processo)['ator']);
    }

    public function test_servidor_continua_resolvido_e_ator_oculto_continua_escondido(): void
    {
        config()->set('viabiliza.tramitacao.atores_ocultos.emails', ['suporte@exemplo.test']);
        $servidor = User::factory()->create(['name' => 'Analista Visivel']);
        $suporte = User::factory()->create(['email' => 'suporte@exemplo.test']);
        $processoDoServidor = ViabilityRequest::factory()->create();
        $processoDoSuporte = ViabilityRequest::factory()->create();

        $this->anexadoPor($processoDoServidor, $servidor);
        $this->anexadoPor($processoDoSuporte, $suporte);

        $this->assertSame('Analista Visivel', $this->documentoAnexado($processoDoServidor)['ator']);
        $this->assertNull($this->documentoAnexado($processoDoSuporte)['ator']);
    }
}
