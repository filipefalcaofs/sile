<?php

namespace Tests\Feature\Seeders;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Models\DecisionText;
use App\Models\LouosQuadro7Faixa;
use App\Services\Analise\JustificativaFundamentadaComposer;
use App\Services\Auditoria\DecisionExplanationService;
use App\Services\Decisao\DecisionTextCatalog;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\LouosEnquadramentoService;
use App\Services\Viabilidade\ConsultaViabilidadeService;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use ReflectionClassConstant;
use ReflectionMethod;
use Tests\TestCase;

class DecisionTextSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_popula_todas_as_chaves_do_catalogo(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $defaults = DecisionTextCatalog::defaults();

        $this->assertSame(count($defaults), DecisionText::query()->count());

        foreach ($defaults as $key => $template) {
            $texto = DecisionText::query()->where('key', $key)->first();

            $this->assertNotNull($texto, "A chave {$key} não foi seedada.");
            $this->assertSame($template, $texto->template, "O template de {$key} diverge dos defaults.");
            $this->assertNotEmpty($texto->description, "A chave {$key} ficou sem descrição.");
            $this->assertLessThan(
                255,
                mb_strlen($texto->description),
                "A descrição de {$key} estoura o varchar(255) do Postgres.",
            );
        }
    }

    public function test_seeder_e_idempotente_e_preserva_texto_administrado(): void
    {
        $this->seed(DecisionTextSeeder::class);

        DecisionText::query()
            ->where('key', 'louos.motivo.proibido')
            ->firstOrFail()
            ->update(['template' => 'Texto editado pela SEDUR']);

        $this->seed(DecisionTextSeeder::class);

        $this->assertSame(count(DecisionTextCatalog::defaults()), DecisionText::query()->count());
        $this->assertSame(
            'Texto editado pela SEDUR',
            DecisionText::query()->where('key', 'louos.motivo.proibido')->firstOrFail()->template,
        );
    }

    /**
     * Golden permanente dos defaults: o valor seedado é byte-idêntico à
     * constante que hoje vive no código. Se o código mudar o texto sem
     * atualizar o catálogo, este teste quebra — é a rede da byte-identidade.
     */
    public function test_paridade_byte_identica_com_as_constantes_do_codigo(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $catalogo = new DecisionTextCatalog;

        $this->assertSame(
            self::constante(LouosEnquadramentoService::class, 'FUNDAMENTO_LOUOS'),
            $catalogo->get('base_legal.louos'),
        );
        $this->assertSame(
            self::constante(LouosEnquadramentoService::class, 'MOTIVO_PROIBIDO'),
            $catalogo->get('louos.motivo.proibido'),
        );
        $this->assertSame(
            self::constante(ConsultaViabilidadeService::class, 'AVISO_ZONA_PENDENTE'),
            $catalogo->get('consulta.aviso.zona_pendente'),
        );
        $this->assertSame(
            self::constante(DecisionExplanationService::class, 'TITULO_RISCO'),
            $catalogo->get('explicacao.titulo.risco'),
        );
    }

    public function test_paridade_da_conclusao_da_justificativa(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $composer = new JustificativaFundamentadaComposer;
        $conclusao = new ReflectionMethod($composer, 'conclusao');

        $atual = $conclusao->invoke($composer, [
            'consolidado' => ['resultado' => ResultadoViabilidade::Permitido->value],
            'zona' => 'ZEC',
        ]);

        $doCatalogo = (new DecisionTextCatalog)->render(
            'justificativa.conclusao.permitido',
            [':zona' => 'ZEC'],
        );

        $this->assertSame($atual, $doCatalogo);
    }

    public function test_paridade_do_template_do_quadro10(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $service = app(LouosEnquadramentoService::class);
        $motivo = new ReflectionMethod($service, 'motivoQuadro10');

        $atual = $motivo->invoke($service, ['grupo' => 'A1'], ['nome' => 'ZEC'], Quadro10Permissao::Permitido);

        $doCatalogo = (new DecisionTextCatalog)->render('louos.template.quadro10', [
            ':grupo' => 'A1',
            ':permissao' => mb_strtolower(Quadro10Permissao::Permitido->label()),
            ':zona' => 'ZEC',
        ]);

        $this->assertSame($atual, $doCatalogo);
    }

    public function test_paridade_do_template_do_quadro7(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $service = app(LouosEnquadramentoService::class);
        $motivo = new ReflectionMethod($service, 'motivoQuadro7');
        $faixa = new LouosQuadro7Faixa([
            'grupo' => 'A1',
            'subgrupo' => 'nR1-01',
            'area_min' => 0,
            'area_max' => 200,
        ]);

        $atual = $motivo->invoke($service, '4711301', 100.0, $faixa);

        $doCatalogo = (new DecisionTextCatalog)->render('louos.template.quadro7', [
            ':cnae' => '4711-3/01',
            ':area' => '100',
            ':grupo' => 'A1',
            ':subgrupo' => ' (nR1-01)',
            ':faixa' => ' (faixa 0 a 200 m²)',
        ]);

        $this->assertSame($atual, $doCatalogo);
    }

    public function test_paridade_do_template_permitido_com_a_condicao_embutida(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $service = app(LouosEnquadramentoService::class);
        $motivo = new ReflectionMethod($service, 'motivoPermitido');
        $input = new EnquadramentoInput(area: 100.0, cnaePrincipal: '4711301');

        $atual = $motivo->invoke($service, ['grupo' => 'A1'], $input, false);

        $catalogo = new DecisionTextCatalog;
        $doCatalogo = $catalogo->render('louos.template.permitido', [
            ':cnae' => '4711-3/01',
            ':area' => '100',
            ':grupo' => 'A1',
            ':zona' => 'a zona identificada',
            // O motor aplica rtrim(..., '.') ao embutir a condição — o template
            // pai é quem carrega o ponto final (nota de composição do plano).
            ':condicao' => rtrim($catalogo->get('louos.motivo.permitido'), '.'),
        ]);

        $this->assertSame($atual, $doCatalogo);
    }

    private static function constante(string $class, string $nome): string
    {
        return (string) (new ReflectionClassConstant($class, $nome))->getValue();
    }
}
