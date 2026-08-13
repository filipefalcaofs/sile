<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\ClassificacaoDocumentoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\DocumentRequirement;
use App\Models\ViabilityRequestDocument;
use App\Services\Ai\ClassificacaoDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-113 (classificação documental): a IA sugere a categoria do documento e
 * confronta com a exigência esperada — sempre como SUGESTÃO com ALERTA, nunca
 * bloqueando o protocolo (RN-005, complementa e não substitui o humano). Prova:
 * classifica com categoria/confiança/fonte; categoria incompatível vira alerta
 * sem bloquear; toggle features.ia_classificacao OFF ⇒ NÃO chama o provedor e
 * NÃO cria sugestão (degradação honesta). Fake por agente — sem rede/custo.
 */
#[Group('ia')]
class ClassificacaoDocumentoTest extends TestCase
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
     * @param  array<string, mixed>  $atributos
     */
    private function documento(array $atributos = []): ViabilityRequestDocument
    {
        Storage::fake('local');

        $doc = ViabilityRequestDocument::factory()->create(array_merge([
            'disk' => 'local',
            'path' => 'solicitacoes/fachada.jpg',
            'mime_type' => 'image/jpeg',
        ], $atributos));

        Storage::disk('local')->put($doc->path, 'conteudo-binario-falso');

        return $doc;
    }

    private function service(): ClassificacaoDocumentoService
    {
        return app(ClassificacaoDocumentoService::class);
    }

    public function test_classifica_documento_com_categoria_confianca_e_fonte(): void
    {
        config(['sile.features.ia_classificacao' => true]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        ClassificacaoDocumentoAgent::fake([[
            'categoria' => 'fachada',
            'confianca' => 'alta',
            'compativel_com_exigencia' => true,
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $despachou = $this->service()->processar($doc, userId: null);

        $this->assertTrue($despachou);
        ClassificacaoDocumentoAgent::assertPrompted(fn ($prompt) => $prompt->prompt !== '');

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Classificacao, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame('fachada', $sugestao->output['categoria']);
        $this->assertSame('alta', $sugestao->confidence);
        $this->assertSame($doc->viability_request_id, $sugestao->viability_request_id);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-classificacao',
            'result' => 'sucesso',
        ]);
    }

    public function test_categoria_incompativel_com_a_exigencia_gera_alerta_sem_bloquear(): void
    {
        config(['sile.features.ia_classificacao' => true]);
        $this->provedorVisaoAtivo();

        $exigencia = DocumentRequirement::factory()->create([
            'code' => 'foto-fachada',
            'name' => 'Foto da fachada do imóvel',
        ]);
        $doc = $this->documento(['requirement_id' => $exigencia->id]);

        ClassificacaoDocumentoAgent::fake([[
            'categoria' => 'contrato_locacao',
            'confianca' => 'alta',
            'compativel_com_exigencia' => false,
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $this->service()->processar($doc);

        // A exigência esperada é levada ao agente (confronto real, não fachada).
        ClassificacaoDocumentoAgent::assertPrompted(
            fn ($prompt) => str_contains($prompt->prompt, 'Foto da fachada do imóvel'),
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Classificacao, $sugestao->type);
        // Alerta é dado revisável, nunca bloqueio do protocolo (RN-005).
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertFalse($sugestao->output['compativel_com_exigencia']);
        $this->assertSame('contrato_locacao', $sugestao->output['categoria']);
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_classificacao' => false]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        ClassificacaoDocumentoAgent::fake([[
            'categoria' => 'fachada',
            'confianca' => 'alta',
            'fonte' => 'documento',
        ]]);

        $despachou = $this->service()->processar($doc);

        $this->assertFalse($despachou);
        ClassificacaoDocumentoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }
}
