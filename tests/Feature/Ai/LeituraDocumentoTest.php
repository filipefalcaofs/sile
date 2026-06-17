<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\LeituraDocumentoAgent;
use App\Enums\AiSuggestionStatus;
use App\Enums\AiSuggestionType;
use App\Models\AiConfiguration;
use App\Models\AiSuggestion;
use App\Models\ViabilityRequestDocument;
use App\Services\Ai\LeituraDocumentoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\StoredDocument;
use Laravel\Ai\Files\StoredImage;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * HU-112 (OCR) + HU-114 (ilegibilidade): leitura por visão de um documento da
 * solicitação como SUGESTÃO revisável (nunca verdade). Prova: documento legível
 * vira sugestão ocr com texto/confiança/fonte; ilegibilidade (legivel=false) ou
 * confiança abaixo do limiar escala para humano; toggle features.ia_ocr OFF ⇒
 * NÃO chama o provedor e NÃO cria sugestão (degradação honesta, anti-fachada).
 * Fake por agente — sem rede, sem custo.
 */
#[Group('ia')]
class LeituraDocumentoTest extends TestCase
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

    private function service(): LeituraDocumentoService
    {
        return app(LeituraDocumentoService::class);
    }

    public function test_documento_legivel_gera_sugestao_ocr_sugerida_com_texto_confianca_e_fonte(): void
    {
        config(['sile.features.ia_ocr' => true]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        LeituraDocumentoAgent::fake([[
            'texto' => 'CONTRATO SOCIAL — Empresa Exemplo LTDA',
            'legivel' => true,
            'confianca' => 'alta',
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $despachou = $this->service()->processar($doc, userId: null);

        $this->assertTrue($despachou);
        LeituraDocumentoAgent::assertPrompted(
            fn ($prompt) => $prompt->attachments->first() instanceof StoredImage,
        );

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionType::Ocr, $sugestao->type);
        $this->assertSame(AiSuggestionStatus::Sugerida, $sugestao->status);
        $this->assertSame('CONTRATO SOCIAL — Empresa Exemplo LTDA', $sugestao->output['texto']);
        $this->assertTrue($sugestao->output['legivel']);
        $this->assertSame('alta', $sugestao->confidence);
        $this->assertSame($doc->viability_request_id, $sugestao->viability_request_id);
        $this->assertSame(
            ['document_id' => $doc->id, 'disk' => 'local', 'path' => 'solicitacoes/fachada.jpg'],
            $sugestao->input_ref,
        );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'ia',
            'event' => 'ia-ocr',
            'result' => 'sucesso',
            'personal_data' => true,
        ]);
    }

    public function test_documento_ilegivel_escala_para_humano_mesmo_com_confianca_alta(): void
    {
        config(['sile.features.ia_ocr' => true]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        // legivel=false com confiança alta: a ilegibilidade — não a confiança —
        // é quem força a revisão humana (HU-114), sem tratar a leitura como verdade.
        LeituraDocumentoAgent::fake([[
            'texto' => '',
            'legivel' => false,
            'confianca' => 'alta',
            'motivo_ilegibilidade' => 'documento borrado e cortado',
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $this->service()->processar($doc);

        $sugestao = AiSuggestion::query()->sole();
        $this->assertSame(AiSuggestionStatus::EscaladaHumano, $sugestao->status);
        $this->assertFalse($sugestao->output['legivel']);
    }

    public function test_confianca_abaixo_do_limiar_escala_para_humano(): void
    {
        config(['sile.features.ia_ocr' => true]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        LeituraDocumentoAgent::fake([[
            'texto' => 'texto parcial e incerto',
            'legivel' => true,
            'confianca' => 'baixa',
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $this->service()->processar($doc);

        $this->assertSame(AiSuggestionStatus::EscaladaHumano, AiSuggestion::query()->sole()->status);
    }

    public function test_documento_pdf_usa_anexo_de_documento(): void
    {
        config(['sile.features.ia_ocr' => true]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento([
            'path' => 'solicitacoes/contrato.pdf',
            'mime_type' => 'application/pdf',
        ]);

        LeituraDocumentoAgent::fake([[
            'texto' => 'CONTRATO DE LOCAÇÃO',
            'legivel' => true,
            'confianca' => 'alta',
            'fonte' => 'documento anexado pelo requerente',
        ]]);

        $this->service()->processar($doc);

        LeituraDocumentoAgent::assertPrompted(
            fn ($prompt) => $prompt->attachments->first() instanceof StoredDocument,
        );
    }

    public function test_toggle_desligado_nao_chama_provedor_nem_cria_sugestao(): void
    {
        config(['sile.features.ia_ocr' => false]);
        $this->provedorVisaoAtivo();
        $doc = $this->documento();

        LeituraDocumentoAgent::fake([[
            'texto' => 'qualquer',
            'legivel' => true,
            'confianca' => 'alta',
            'fonte' => 'documento',
        ]]);

        $despachou = $this->service()->processar($doc);

        $this->assertFalse($despachou);
        LeituraDocumentoAgent::assertNeverPrompted();
        $this->assertSame(0, AiSuggestion::query()->count());
    }
}
