<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\Activity;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro7Faixa;
use App\Models\Parameter;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Louos\LouosSandboxSimulationService;
use App\Services\Rules\RuleVersionService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Sandbox de simulação de impacto de parametrização da LOUOS (HU-143). O gestor
 * simula uma versão RASCUNHO de um Quadro contra cenários reais — derivados dos
 * CNAEs da versão vigente do Quadro 7 com a zona fixa do Quadro 10 — reexecutando
 * o MOTOR REAL (LouosEnquadramentoService) por versão específica e contando as
 * divergências (resultado vigente × simulado), antes de publicar por quatro olhos.
 *
 * RN-001 (zero efeito colateral): a simulação NÃO altera a versão vigente, NÃO
 * publica, NÃO audita decisão e NÃO dispara integração/notificação — só leitura
 * e cálculo. Anti-fachada: não imita o resultado, reexecuta o motor de verdade.
 */
class LouosSandboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function sandbox(): LouosSandboxSimulationService
    {
        return app(LouosSandboxSimulationService::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * Versão vigente do Quadro 7 com as faixas informadas (chave do cenário: o
     * CNAE real da regra vigente).
     *
     * @param  list<array<string, mixed>>  $faixas
     */
    private function seedQuadro7Vigente(array $faixas): RuleVersion
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro7,
            'version' => 'q7-vigente',
            'rules_version' => 'q7-vigente',
        ]);

        foreach ($faixas as $faixa) {
            LouosQuadro7Faixa::factory()->create([
                'rule_version_id' => $version->id,
                'cnae_code' => $faixa['cnae'],
                'grupo' => $faixa['grupo'],
                'subgrupo' => $faixa['subgrupo'] ?? null,
                'area_min' => $faixa['area_min'],
                'area_max' => $faixa['area_max'] ?? null,
            ]);
        }

        return $version;
    }

    /**
     * Versão vigente do Quadro 10 com as permissões informadas (de onde o sandbox
     * deriva a zona fixa dos cenários).
     *
     * @param  list<array<string, mixed>>  $permissoes
     */
    private function seedQuadro10Vigente(array $permissoes): RuleVersion
    {
        $version = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'q10-vigente',
            'rules_version' => 'q10-vigente',
        ]);

        foreach ($permissoes as $permissao) {
            LouosQuadro10Permissao::factory()->create([
                'rule_version_id' => $version->id,
                'zona' => $permissao['zona'],
                'grupo_uso' => $permissao['grupo_uso'],
                'subgrupo' => $permissao['subgrupo'] ?? '',
                'permissao' => $permissao['permissao'],
            ]);
        }

        return $version;
    }

    /**
     * Abre um rascunho REAL do Quadro 10 (RuleVersionService::openDraft) copiando
     * as permissões da vigente — opcionalmente trocando a permissão de uma
     * (zona × grupo de uso). Mesma mecânica do mantenedor (05-06), sem publicar.
     *
     * @param  array{zona: string, grupo_uso: string, permissao: Quadro10Permissao}|null  $flip
     */
    private function abrirRascunhoQuadro10(string $versao, int $authorId, ?array $flip = null): RuleVersion
    {
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();

        $draft = app(RuleVersionService::class)->openDraft(
            RuleDomain::LouosQuadro10,
            $versao,
            'Rascunho de sandbox (teste)',
            $authorId,
        );

        LouosQuadro10Permissao::query()
            ->where('rule_version_id', $vigente->id)
            ->get()
            ->each(function (LouosQuadro10Permissao $permissao) use ($draft, $flip): void {
                $valor = $permissao->permissao;

                if ($flip !== null && $permissao->zona === $flip['zona'] && $permissao->grupo_uso === $flip['grupo_uso']) {
                    $valor = $flip['permissao'];
                }

                LouosQuadro10Permissao::factory()->create([
                    'rule_version_id' => $draft->id,
                    'zona' => $permissao->zona,
                    'grupo_uso' => $permissao->grupo_uso,
                    'subgrupo' => $permissao->subgrupo,
                    'permissao' => $valor,
                    'condicionante_ref' => $permissao->condicionante_ref,
                    'base_legal' => $permissao->base_legal,
                ]);
            });

        return $draft;
    }

    public function test_simulacao_nao_altera_a_versao_vigente(): void
    {
        // RN-001: simular NÃO afeta a vigente — id da vigente inalterado, o
        // rascunho segue rascunho e nenhuma linha da vigente muda.
        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'subgrupo' => null, 'area_min' => 0, 'area_max' => 350],
        ]);
        $q10Vigente = $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $draft = $this->abrirRascunhoQuadro10('q10-rascunho', $author->id, [
            'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Proibido,
        ]);

        $vigenteAntes = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();
        $permissaoVigente = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $q10Vigente->id)
            ->where('zona', 'ZPR-1')
            ->where('grupo_uso', 'nR1')
            ->firstOrFail();

        $this->sandbox()->simulate(RuleDomain::LouosQuadro10, 'q10-rascunho');

        $vigenteDepois = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();
        $this->assertSame($vigenteAntes->id, $vigenteDepois->id);
        $this->assertSame('q10-vigente', $vigenteDepois->version);

        $this->assertSame(RuleVersionStatus::Rascunho, $draft->fresh()->status);

        // a permissão da vigente (permitido) permanece intacta — a simulação não a tocou
        $this->assertSame(Quadro10Permissao::Permitido, $permissaoVigente->fresh()->permissao);
    }

    public function test_simulacao_reexecuta_o_motor_e_conta_divergencias(): void
    {
        // ÂNCORA anti-fachada: os cenários vêm dos CNAEs REAIS da versão vigente
        // do Quadro 7, com a zona fixa do Quadro 10. O rascunho do Quadro 10 troca
        // permitido→proibido na (zona × grupo) dos cenários → o motor reexecutado
        // conta ≥ 1 divergência determinística (permitido vigente × nao_permitido
        // simulado). Prova que a simulação reexecuta o motor, não imita resultado.
        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'subgrupo' => null, 'area_min' => 0, 'area_max' => 350],
        ]);
        $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $this->abrirRascunhoQuadro10('q10-rascunho', $author->id, [
            'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Proibido,
        ]);

        $relatorio = $this->sandbox()->simulate(RuleDomain::LouosQuadro10, 'q10-rascunho');

        $this->assertSame('louos_quadro10', $relatorio['dominio']);
        $this->assertSame('q10-rascunho', $relatorio['versao_rascunho']);
        $this->assertGreaterThanOrEqual(1, $relatorio['mudariam']);
        $this->assertArrayHasKey('permitido→nao_permitido', $relatorio['distribuicao']);

        $divergencia = collect($relatorio['divergencias'])
            ->firstWhere('cenario.cnae', '4712100');

        $this->assertNotNull($divergencia);
        $this->assertSame(ResultadoViabilidade::Permitido->value, $divergencia['resultado_vigente']);
        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $divergencia['resultado_simulado']);
        $this->assertSame('ZPR-1', $divergencia['cenario']['zona']);
    }

    public function test_amostra_e_parametrizavel_e_consta_no_resumo(): void
    {
        // RN-003/HU-014: o tamanho da amostra é parametrizável (sem hardcode) e
        // consta no resumo. Explícita tem precedência; ausente lê Settings.
        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 350],
            ['cnae' => '4721102', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 250],
            ['cnae' => '4772500', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => null],
        ]);
        $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $this->abrirRascunhoQuadro10('q10-rascunho', $author->id);

        $explicita = $this->sandbox()->simulate(RuleDomain::LouosQuadro10, 'q10-rascunho', 2);
        $this->assertSame(2, $explicita['amostra_usada']);
        $this->assertSame(2, $explicita['total']);

        // sem amostra explícita → lê o parâmetro louos.sandbox.amostra_padrao
        // (efeito sem deploy: Parameter::saved invalida o cache).
        Parameter::query()->create([
            'key' => 'louos.sandbox.amostra_padrao',
            'group' => 'louos',
            'type' => 'integer',
            'value' => '7',
            'default_value' => '50',
            'validation_rules' => ['required', 'integer', 'min:1'],
            'description' => 'Amostra padrão do sandbox de parametrização (HU-143).',
        ]);

        $padrao = $this->sandbox()->simulate(RuleDomain::LouosQuadro10, 'q10-rascunho');
        $this->assertSame(7, $padrao['amostra_usada']);
        $this->assertSame(3, $padrao['total']); // só 3 CNAEs distintos disponíveis
    }

    public function test_simulacao_nao_dispara_efeito_colateral(): void
    {
        // RN-001: a simulação reexecuta o motor mas NÃO persiste decisão de
        // enquadramento, NÃO publica versão e NÃO dispara notificação.
        Notification::fake();

        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 350],
        ]);
        $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $this->abrirRascunhoQuadro10('q10-rascunho', $author->id, [
            'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Proibido,
        ]);

        $this->sandbox()->simulate(RuleDomain::LouosQuadro10, 'q10-rascunho');

        // nenhuma decisão de enquadramento persistida (o motor roda em sandbox)
        $this->assertSame(0, Activity::query()->where('event', 'enquadramento')->count());
        // nenhuma publicação de versão disparada pela simulação
        $this->assertSame(0, Activity::query()->where('event', 'publicacao-versao')->count());

        Notification::assertNothingSent();
    }

    public function test_gestor_simula_pela_rota_sem_publicar(): void
    {
        // CA-01: o gestor simula pela rota e recebe o relatório; a versão vigente
        // permanece intacta (a rota simula, não publica).
        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 350],
        ]);
        $q10Vigente = $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $this->abrirRascunhoQuadro10('q10-rascunho', $author->id, [
            'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Proibido,
        ]);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/louos/simulacao', [
                'quadro' => 'quadro10',
                'versao_rascunho' => 'q10-rascunho',
            ])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/louos/sandbox')
                ->where('simulacao.dominio', 'louos_quadro10')
                ->where('simulacao.versao_rascunho', 'q10-rascunho')
                ->where('simulacao.mudariam', 1)
                ->has('simulacao.divergencias', 1));

        // vigente intacta: mesma versão, sem nova versão promovida
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();
        $this->assertSame($q10Vigente->id, $vigente->id);
        $this->assertSame('q10-vigente', $vigente->version);
    }

    public function test_publicar_exige_quatro_olhos(): void
    {
        // RN-005 (HU-046/HU-053): autor = publicador é bloqueado (flash.error) e
        // o rascunho segue rascunho; publicador distinto promove a nova vigente.
        $autor = $this->administrador();
        $publicador = $this->administrador();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 350],
        ]);
        $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $draft = $this->abrirRascunhoQuadro10('q10-rascunho', $autor->id, [
            'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Proibido,
        ]);

        // autor = publicador → bloqueio de quatro olhos, rascunho preservado
        $this->actingAs($autor, 'gestao')
            ->put('/gestao/louos/simulacao/publicar', [
                'quadro' => 'quadro10',
                'versao_rascunho' => 'q10-rascunho',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(RuleVersionStatus::Rascunho, $draft->fresh()->status);
        $this->assertSame('q10-vigente', RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail()->version);

        // publicador distinto → nova versão vigente
        $this->actingAs($publicador, 'gestao')
            ->put('/gestao/louos/simulacao/publicar', [
                'quadro' => 'quadro10',
                'versao_rascunho' => 'q10-rascunho',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->firstOrFail();
        $this->assertSame('q10-rascunho', $vigente->version);
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->status);
    }

    public function test_simulacao_sem_permissao_manter_louos_bloqueada(): void
    {
        // CA-04: simular/publicar é manutenção (manter-louos). O analista consulta
        // os Quadros mas NÃO mantém — é bloqueado (403) no sandbox.
        $author = User::factory()->create();

        $this->seedQuadro7Vigente([
            ['cnae' => '4712100', 'grupo' => 'nR1', 'area_min' => 0, 'area_max' => 350],
        ]);
        $this->seedQuadro10Vigente([
            ['zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'permissao' => Quadro10Permissao::Permitido],
        ]);
        $this->abrirRascunhoQuadro10('q10-rascunho', $author->id);

        $analista = $this->analista();

        $this->actingAs($analista, 'gestao')
            ->get('/gestao/louos/simulacao')
            ->assertForbidden();

        $this->actingAs($analista, 'gestao')
            ->post('/gestao/louos/simulacao', [
                'quadro' => 'quadro10',
                'versao_rascunho' => 'q10-rascunho',
            ])
            ->assertForbidden();
    }
}
