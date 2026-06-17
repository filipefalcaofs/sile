<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\InconsistenciasAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\DocumentRequirement;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestDocument;
use App\Services\Ai\InconsistenciasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredImage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-115 (detecção de inconsistências): a IA confronta os dados DECLARADOS no
 * processo (endereço/área/CNAE) com o que a FOTO da fachada e os documentos
 * mostram, e devolve cada divergência com a fonte — sempre como SUGESTÃO que
 * SINALIZA, nunca decisão nem punição automática (RN-004). COMPLEMENTA, não
 * substitui, as validações determinísticas (HU-063/037/105 — RN-005). Prova:
 * detecta inconsistências com fonte (status sugerida); inconsistência sem fonte
 * escala para humano; toggle features.ia_inconsistencias OFF ⇒ NÃO chama o
 * provedor e NÃO cria sugestão (degradação honesta). Fake por agente — sem
 * rede/custo.
 */
#[Group('ia')]
class InconsistenciasTest extends TestCase
{
    use RefreshDatabase;

    private function provedorVisaoAtivo(): void
    {
        AiConfiguration::factory()->create([
            'provider' => 'openai',
            'capability' => 'vision',
            'model' => 'gpt-5.4-vision',
            'active' => true,
            'is_default' => true,
        ]);
    }

    /**
     * Processo com dados declarados e a foto de fachada anexada (a entrada real
     * do confronto documento × declaração).
     */
    private function processoComFachada(): ViabilityRequest
    {
        Storage::fake('local');

        $processo = ViabilityRequest::factory()->withPrimaryCnae()->create([
            'used_area_m2' => 50,
            'address_street' => 'Rua das Flores',
            'address_number' => '100',
            'address_neighborhood' => 'Centro',
        ]);

        $exigencia = DocumentRequirement::factory()->create([
            'code' => 'foto-fachada',
            'name' => 'Foto da fachada do imóvel',
        ]);

        $doc = ViabilityRequestDocument::factory()->create([
            'viability_request_id' => $processo->id,
            'requirement_id' => $exigencia->id,
            'disk' => 'local',
            'path' => 'solicitacoes/fachada.jpg',
            'mime_type' => 'image/jpeg',
        ]);

        Storage::disk('local')->put($doc->path, 'conteudo-binario-falso');

        return $processo;
    }

    private function service(): InconsistenciasService
    {
        return app(InconsistenciasService::class);
    }

    public function test_detecta_inconsistencias_com_fonte_como_sugestao(): void
    {
        config(['sile.features.ia_inconsistencias' => true]);
        $this->provedorVisaoAtivo();
        $processo = $this->processoComFachada();

        InconsistenciasAgent::fake([[
            'inconsistencias' => [
                [
                    'campo' => 'area',
                    'declarado' => '50 m²',
                    'documento' => 'fachada sugere imóvel maior, ~80 m²',
                    'severidade' => 'media',
                ],
                [
                    'campo' => 'endereco_numero',
                    'declarado' => '100',
                    'documento' => 'placa na fachada indica número 102',
                    'severidade' => 'alta',
                ],
            ],
            'fonte' => 'foto da fachada anexada pelo requerente',
        ]]);

        $despachou = $this->service()->processar($processo, userId: null);

        $this->assertTrue($despachou);
        // A foto da fachada é levada ao agente (confronto real, não fachada).
        InconsistenciasAgent::assertPrompted(
            fn ($prompt) => $prompt->attachments->first() instanceof StoredImage,
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Inconsistencias, $sugestao->type);
        // Sinaliza, nunca decide nem pune (RN-004).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertCount(2, $sugestao->output['inconsistencias']);
        $this->assertSame('area', $sugestao->output['inconsistencias'][0]['campo']);
        $this->assertSame('alta', $sugestao->output['inconsistencias'][1]['severidade']);
        $this->assertSame($processo->id, $sugestao->viability_request_id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-inconsistencias',
            'result' => 'sucesso',
            'personal_data' => true,
        ]);
    }

    public function test_inconsistencia_sem_fonte_escala_para_humano(): void
    {
        config(['sile.features.ia_inconsistencias' => true]);
        $this->provedorVisaoAtivo();
        $processo = $this->processoComFachada();

        // Sem fonte rastreável a sugestão não pode ser aceita às cegas: nasce
        // escalada para revisão humana (guardrail do RunAiAgentJob).
        InconsistenciasAgent::fake([[
            'inconsistencias' => [
                [
                    'campo' => 'cnae',
                    'declarado' => 'comércio varejista',
                    'documento' => 'fachada sugere atividade industrial',
                    'severidade' => 'alta',
                ],
            ],
        ]]);

        $this->service()->processar($processo);

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Inconsistencias, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::EscaladaHumano, $sugestao->status);
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_inconsistencias' => false]);
        $this->provedorVisaoAtivo();
        $processo = $this->processoComFachada();

        InconsistenciasAgent::fake([[
            'inconsistencias' => [
                ['campo' => 'area', 'declarado' => '50', 'documento' => '80', 'severidade' => 'media'],
            ],
            'fonte' => 'documento',
        ]]);

        $despachou = $this->service()->processar($processo);

        $this->assertFalse($despachou);
        InconsistenciasAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }
}
