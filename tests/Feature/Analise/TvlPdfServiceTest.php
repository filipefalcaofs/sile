<?php

namespace Tests\Feature\Analise;

use App\Enums\ViabilityRequestStatus;
use App\Models\Activity;
use App\Models\AnalysisRecord;
use App\Models\Cnae;
use App\Models\TvlDocument;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Analise\TvlPdfService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * TVL em PDF no backoffice (HU-132): o TvlPdfService renderiza o Termo de
 * Viabilidade de Localização DE VERDADE (dompdf sobre um Blade real — o conteúdo
 * gravado começa com '%PDF'), SÓ para decisões deferidas (FA-01: indeferida/em
 * análise → bloqueio), no disco parametrizado e NUNCA público, registrando cada
 * emissão/reimpressão em tvl_documents auditado (RN-002/CA-03). A fonte é a MESMA
 * ViabilityDecision deferida (fluxo expresso OU análise humana) + as condicionantes
 * da ficha finalizada. O download (URL assinada) e o gate de perfil emitir-tvl são
 * do endpoint (10-15); aqui o serviço é route-free. O PDF NÃO vai ao cidadão
 * (CA-02 — relatório administrativo interno).
 */
class TvlPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // O disco padrão do TVL (config sile.analise.tvl.disk) é 'local'; o teste
        // grava no disco fake para provar a escrita real sem tocar o filesystem.
        Storage::fake('local');
    }

    private function service(): TvlPdfService
    {
        return app(TvlPdfService::class);
    }

    /**
     * Decisão DEFERIDA padrão (fluxo expresso do factory) — número TVL, per_cnae e
     * fundamentação plausíveis. $override permite ajustar o cenário.
     *
     * @param  array<string, mixed>  $override
     */
    private function decisaoDeferida(array $override = []): ViabilityDecision
    {
        return ViabilityDecision::factory()->create($override);
    }

    /**
     * Decisão DEFERIDA da análise humana (flow analise_tecnica) com a ficha
     * finalizada que carrega as condicionantes exibidas no TVL (10-09/10-10).
     *
     * @param  list<string>  $condicionantes
     * @param  list<array<string, mixed>>  $perCnae
     */
    private function decisaoDeferidaComFicha(array $condicionantes, array $perCnae): ViabilityDecision
    {
        $request = ViabilityRequest::factory()->protocoled()->create();
        $request->forceFill(['status' => ViabilityRequestStatus::EmAnalise])->save();

        AnalysisRecord::factory()->finalizada()->create([
            'viability_request_id' => $request->id,
            'revision' => 1,
            'conditions' => $condicionantes,
            'per_cnae' => $perCnae,
        ]);

        return ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'flow' => 'analise_tecnica',
            'per_cnae' => $perCnae,
        ]);
    }

    public function test_gera_pdf_real_no_disco_e_registra_tvl_document_auditado(): void
    {
        // CA-01/CA-03 + anti-fachada: dompdf renderiza o Blade e grava um arquivo
        // que COMEÇA com '%PDF' no disco (não um HTML simulado); cada emissão cria
        // uma linha tvl_documents (verification_code único, generated_by) e uma
        // auditoria 'analise'/'tvl-emitido'.
        $ator = User::factory()->create();
        $decision = $this->decisaoDeferida();

        $document = $this->service()->generate($decision, $ator);

        $this->assertInstanceOf(TvlDocument::class, $document);
        $this->assertSame($decision->id, $document->viability_decision_id);
        $this->assertSame($ator->id, $document->generated_by_user_id);
        $this->assertNotNull($document->verification_code);
        $this->assertNotNull($document->generated_at);

        Storage::disk($document->disk)->assertExists($document->path);
        $bytes = Storage::disk($document->disk)->get($document->path);
        $this->assertStringStartsWith('%PDF', $bytes);

        $this->assertDatabaseCount('tvl_documents', 1);

        $activity = Activity::query()
            ->where('log_name', 'analise')
            ->where('event', 'tvl-emitido')
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'A emissão do TVL deve ser auditada (RN-002/CA-03).');
        $this->assertSame('sucesso', $activity->result);
        $this->assertSame($decision->viability_request_id, $activity->properties['viability_request_id']);
        $this->assertSame($decision->tvl_product_number, $activity->properties['tvl_product_number']);
        $this->assertSame($document->verification_code, $activity->properties['verification_code']);
    }

    public function test_bloqueia_emissao_de_decisao_indeferida(): void
    {
        // FA-01: TVL só para deferida. Indeferida → DomainException; nada gerado
        // (nem arquivo, nem linha, nem auditoria).
        $decision = ViabilityDecision::factory()->indeferida()->create();

        try {
            $this->service()->generate($decision, User::factory()->create());
            $this->fail('Esperava DomainException ao emitir TVL de decisão indeferida (FA-01).');
        } catch (DomainException) {
            // esperado
        }

        $this->assertDatabaseCount('tvl_documents', 0);
        $this->assertSame(
            0,
            Activity::query()->where('event', 'tvl-emitido')->count(),
            'Decisão indeferida não pode gerar auditoria de emissão.',
        );
    }

    public function test_reimpressao_cria_nova_linha_e_nova_auditoria(): void
    {
        // CA-03: cada reimpressão é uma nova emissão auditada (nova linha em
        // tvl_documents, novo verification_code/arquivo).
        $decision = $this->decisaoDeferida();
        $ator = User::factory()->create();

        $primeiro = $this->service()->generate($decision, $ator);
        $segundo = $this->service()->generate($decision, $ator);

        $this->assertDatabaseCount('tvl_documents', 2);
        $this->assertNotSame($primeiro->verification_code, $segundo->verification_code);
        $this->assertNotSame($primeiro->path, $segundo->path);

        Storage::disk($primeiro->disk)->assertExists($primeiro->path);
        Storage::disk($segundo->disk)->assertExists($segundo->path);

        $this->assertSame(
            2,
            Activity::query()->where('log_name', 'analise')->where('event', 'tvl-emitido')->count(),
            'Cada emissão/reimpressão deve ser auditada (RN-002/CA-03).',
        );
    }

    public function test_usa_o_disco_parametrizado_e_nunca_o_publico(): void
    {
        // O disco do TVL é parametrizado (analise.tvl.disk) e o default NÃO é
        // 'public': o documento grava no disco configurado (LGPD — nunca exposto).
        $document = $this->service()->generate($this->decisaoDeferida(), User::factory()->create());

        $this->assertNotSame('public', $document->disk);
        $this->assertSame(config('sile.analise.tvl.disk'), $document->disk);
    }

    public function test_recusa_disco_publico_configurado(): void
    {
        // Guarda anti-vazamento: ainda que alguém configure o disco como 'public',
        // o serviço RECUSA emitir (o TVL nunca pode ir a um disco público).
        config()->set('sile.analise.tvl.disk', 'public');

        $this->expectException(RuntimeException::class);

        $this->service()->generate($this->decisaoDeferida(), User::factory()->create());
    }

    public function test_template_contem_numero_atividades_condicionantes_e_fundamentacao(): void
    {
        // CA-01: o documento renderizado traz o número de produto (TVL), as
        // atividades deferidas (CNAE + descrição), as condicionantes da ficha e a
        // fundamentação legal — em pt-BR, com a nota de relatório interno (CA-02).
        Cnae::factory()->create([
            'code' => '4712100',
            'description' => 'Comércio varejista de mercadorias em geral',
        ]);

        $decision = $this->decisaoDeferidaComFicha(
            condicionantes: ['Manter acesso independente para o público.'],
            perCnae: [
                [
                    'cnae' => '4712100',
                    'cnae_formatado' => '4712-1/00',
                    'status_sugerido' => 'deferida',
                    'status_escolhido' => 'deferida',
                    'fundamentacao' => ['Lei nº 9.148/2016 (LOUOS) — Quadro 7'],
                ],
            ],
        );

        $dados = $this->service()->montarDados($decision, 'TVL-COD-VERIFICACAO');
        $html = view('tvl.documento', $dados)->render();

        $this->assertStringContainsString('Termo de Viabilidade de Localização', $html);
        $this->assertStringContainsString((string) $decision->tvl_product_number, $html);
        $this->assertStringContainsString('4712-1/00', $html);
        $this->assertStringContainsString('Comércio varejista de mercadorias em geral', $html);
        $this->assertStringContainsString('Manter acesso independente para o público.', $html);
        $this->assertStringContainsString('Lei nº 9.148/2016 (LOUOS) — Quadro 7', $html);
        $this->assertStringContainsString('TVL-COD-VERIFICACAO', $html);
        // CA-02 — relatório administrativo interno (não vai ao cidadão).
        $this->assertStringContainsString('interno', $html);
    }

    public function test_assinatura_parametrizavel_modo_imagem_embute_a_imagem(): void
    {
        // RN-005: modo 'imagem' com a imagem do diretor configurada → o documento
        // embute a imagem real (data URI base64). gov.br/ICP é gancho (→ SEDUR).
        $png = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
        );
        $caminho = tempnam(sys_get_temp_dir(), 'tvl_assinatura').'.png';
        file_put_contents($caminho, $png);

        config()->set('sile.analise.tvl.assinatura.modo', 'imagem');
        config()->set('sile.analise.tvl.assinatura.imagem_path', $caminho);

        $dados = $this->service()->montarDados($this->decisaoDeferida(), 'TVL-COD');
        $html = view('tvl.documento', $dados)->render();

        $this->assertStringContainsString('data:image', $html);

        @unlink($caminho);
    }

    public function test_assinatura_parametrizavel_modo_nenhuma_nao_assina(): void
    {
        // RN-005: modo 'nenhuma' → nenhuma assinatura embutida (sem forjar firma).
        config()->set('sile.analise.tvl.assinatura.modo', 'nenhuma');

        $dados = $this->service()->montarDados($this->decisaoDeferida(), 'TVL-COD');
        $html = view('tvl.documento', $dados)->render();

        $this->assertStringNotContainsString('data:image', $html);
    }
}
