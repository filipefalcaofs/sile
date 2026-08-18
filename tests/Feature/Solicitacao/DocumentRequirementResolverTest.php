<?php

namespace Tests\Feature\Solicitacao;

use App\Models\Cnae;
use App\Models\DocumentRequirement;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\DocumentRequirementResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Validação documental (HU-067): o resolver calcula os requisitos obrigatórios
 * da solicitação como a UNIÃO dos document_requirements (required, ativos)
 * vinculados aos CNAEs da solicitação com os condicionais BASE — foto da
 * fachada SEMPRE; termo de concessão de uso SE a área é pública. missing() é a
 * base do bloqueio do protocolo (08-10) e do aviso ao requerente. A tabela
 * por-CNAE nasce vazia (carga oficial pendente SEDUR): o resolver degrada
 * honesto, validando os obrigatórios-base conhecidos mesmo sem vínculo por CNAE.
 */
class DocumentRequirementResolverTest extends TestCase
{
    use RefreshDatabase;

    private function resolver(): DocumentRequirementResolver
    {
        return app(DocumentRequirementResolver::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function draft(array $overrides = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->draft()->create($overrides);
    }

    public function test_uniao_dos_requisitos_dos_cnaes(): void
    {
        $cnae1 = Cnae::factory()->create();
        $cnae2 = Cnae::factory()->create();

        $reqA = DocumentRequirement::factory()->required()->create(['code' => 'req-a']);
        $reqB = DocumentRequirement::factory()->required()->create(['code' => 'req-b']);
        $reqCompartilhado = DocumentRequirement::factory()->required()->create(['code' => 'req-compartilhado']);

        $reqA->cnaes()->attach($cnae1);
        $reqCompartilhado->cnaes()->attach($cnae1);
        $reqB->cnaes()->attach($cnae2);
        $reqCompartilhado->cnaes()->attach($cnae2);

        $solicitacao = $this->draft(['is_public_area' => false]);
        $solicitacao->cnaes()->attach([
            $cnae1->id => ['is_primary' => true],
            $cnae2->id => ['is_primary' => false],
        ]);

        $required = $this->resolver()->required($solicitacao);
        $codes = $required->pluck('code')->all();

        $this->assertContains('req-a', $codes);
        $this->assertContains('req-b', $codes);
        $this->assertContains('req-compartilhado', $codes);
        // O requisito compartilhado entre os dois CNAEs aparece UMA única vez.
        $this->assertSame(1, $required->where('code', 'req-compartilhado')->count());
    }

    public function test_fachada_sempre_obrigatoria(): void
    {
        DocumentRequirement::factory()->required()->create(['code' => DocumentRequirementResolver::CODE_FACHADA]);

        $solicitacao = $this->draft(['is_public_area' => false]);

        $codes = $this->resolver()->required($solicitacao)->pluck('code')->all();

        $this->assertContains(DocumentRequirementResolver::CODE_FACHADA, $codes);
    }

    public function test_concessao_so_quando_area_publica(): void
    {
        DocumentRequirement::factory()->required()->create(['code' => DocumentRequirementResolver::CODE_CONCESSAO]);

        $semAreaPublica = $this->draft(['is_public_area' => false]);
        $this->assertNotContains(
            DocumentRequirementResolver::CODE_CONCESSAO,
            $this->resolver()->required($semAreaPublica)->pluck('code')->all(),
        );

        $comAreaPublica = $this->draft(['is_public_area' => true]);
        $this->assertContains(
            DocumentRequirementResolver::CODE_CONCESSAO,
            $this->resolver()->required($comAreaPublica)->pluck('code')->all(),
        );
    }

    public function test_missing_reflete_anexos_existentes(): void
    {
        $fachada = DocumentRequirement::factory()->required()->create(['code' => DocumentRequirementResolver::CODE_FACHADA]);
        $solicitacao = $this->draft(['is_public_area' => false]);

        // Antes de anexar, a fachada consta como faltante.
        $this->assertContains(
            DocumentRequirementResolver::CODE_FACHADA,
            $this->resolver()->missing($solicitacao)->pluck('code')->all(),
        );

        // Anexa o documento que atende o requisito da fachada → some de missing().
        $solicitacao->documents()->create([
            'requirement_id' => $fachada->id,
            'disk' => 'local',
            'path' => 'solicitacoes/'.$solicitacao->id.'/fachada.jpg',
            'original_name' => 'fachada.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'sha256' => hash('sha256', 'conteudo'),
            'uploaded_by_user_id' => $solicitacao->requester_user_id,
        ]);

        $this->assertNotContains(
            DocumentRequirementResolver::CODE_FACHADA,
            $this->resolver()->missing($solicitacao->refresh())->pluck('code')->all(),
        );
    }

    public function test_tabela_por_cnae_vazia_ainda_valida_base(): void
    {
        // Anti-fachada: sem nenhum requisito por-CNAE vinculado (pivot vazio — a
        // realidade atual enquanto a SEDUR não entrega a planilha oficial), o
        // resolver ainda traz o obrigatório-base conhecido (fachada).
        DocumentRequirement::factory()->required()->create(['code' => DocumentRequirementResolver::CODE_FACHADA]);

        $cnae = Cnae::factory()->create();
        $solicitacao = $this->draft(['is_public_area' => false]);
        $solicitacao->cnaes()->attach([$cnae->id => ['is_primary' => true]]);

        $codes = $this->resolver()->required($solicitacao)->pluck('code')->all();

        $this->assertContains(DocumentRequirementResolver::CODE_FACHADA, $codes);
        $this->assertSame([DocumentRequirementResolver::CODE_FACHADA], $codes);
    }
}
