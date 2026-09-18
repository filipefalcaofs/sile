<?php

namespace Tests\Feature\Louos;

use App\Enums\Quadro10Permissao;
use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Exceptions\FourEyesViolationException;
use App\Models\LouosQuadro10Permissao;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\LouosQuadro7Faixa;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Louos\LouosDraftService;
use Database\Seeders\LouosQuadro7Seeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Ciclo de vida do rascunho editável dos Quadros da LOUOS (HU-046):
 * a vigente nunca é tocada; edições acontecem no rascunho; publicação
 * exige quatro olhos; descarte preserva a vigente intacta.
 */
class LouosDraftServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): LouosDraftService
    {
        return app(LouosDraftService::class);
    }

    public function test_abrir_rascunho_materializa_linhas_da_vigente(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $totalVigente = LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count();

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-rascunho-v1', $user->id);

        $this->assertSame(RuleVersionStatus::Rascunho, $draft->status);
        $this->assertSame(
            $totalVigente,
            LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->count(),
        );

        // Vigente intacta
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->fresh()->status);
        $this->assertSame($totalVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());
    }

    public function test_retomar_rascunho_aberto_e_idempotente(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $user = User::factory()->create();

        $draft1 = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-rascunho-v1', $user->id);
        $contagem = LouosQuadro7Faixa::query()->where('rule_version_id', $draft1->id)->count();

        $draft2 = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-rascunho-v2', $user->id);

        $this->assertSame($draft1->id, $draft2->id);
        $this->assertSame($contagem, LouosQuadro7Faixa::query()->where('rule_version_id', $draft1->id)->count());
    }

    public function test_inserir_alterar_e_excluir_linha_so_no_rascunho(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemVigente = LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count();

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-rascunho-v1', $user->id);

        $dados = ['cnae_code' => '9999-9/99', 'area_min' => 0, 'area_max' => 500, 'grupo' => 'nR1', 'subgrupo' => 'nR1-99'];

        $linha = $this->service()->inserirLinha($draft, $dados);
        $this->assertSame($draft->id, $linha->rule_version_id);
        $this->assertDatabaseHas('louos_quadro7_faixas', ['rule_version_id' => $draft->id, 'cnae_code' => '9999999']);

        // Vigente não muda
        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());

        // Chave duplicada lança ValidationException
        try {
            $this->service()->inserirLinha($draft, $dados);
            $this->fail('Era esperado ValidationException para chave duplicada.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('linha', $e->errors());
        }

        // Alterar a linha
        $this->service()->alterarLinha($draft, $linha->id, array_merge($dados, ['grupo' => 'nR2']));
        $linha->refresh();
        $this->assertSame('nR2', $linha->grupo);

        // Vigente ainda intacta
        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());

        // Excluir a linha
        $this->service()->excluirLinha($draft, $linha->id);
        $this->assertDatabaseMissing('louos_quadro7_faixas', ['id' => $linha->id]);

        // Vigente preservada
        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());
    }

    public function test_crud_quadro10_e_quadro11a(): void
    {
        $user = User::factory()->create();

        // --- Quadro 10 ---
        $vigente10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro10-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro10Permissao::factory()->create(['rule_version_id' => $vigente10->id, 'zona' => 'ZPR-1', 'grupo_uso' => 'nR1', 'subgrupo' => '']);
        LouosQuadro10Permissao::factory()->create(['rule_version_id' => $vigente10->id, 'zona' => 'ZPR-2', 'grupo_uso' => 'nR1', 'subgrupo' => '']);
        $contagem10 = 2;

        $draft10 = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, '2026-q10-rascunho', $user->id);
        $this->assertSame($contagem10, LouosQuadro10Permissao::query()->where('rule_version_id', $draft10->id)->count());

        $perm = $this->service()->inserirLinha($draft10, [
            'zona' => 'ZPR-9',
            'grupo_uso' => 'nR3',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Proibido->value,
        ]);
        $this->assertDatabaseHas('louos_quadro10_permissoes', ['rule_version_id' => $draft10->id, 'zona' => 'ZPR-9']);
        $this->assertSame($contagem10, LouosQuadro10Permissao::query()->where('rule_version_id', $vigente10->id)->count());

        $this->service()->alterarLinha($draft10, $perm->id, [
            'zona' => 'ZPR-9',
            'grupo_uso' => 'nR3',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido->value,
        ]);
        $perm->refresh();
        $this->assertSame(Quadro10Permissao::Permitido, $perm->permissao);

        $this->service()->excluirLinha($draft10, $perm->id);
        $this->assertDatabaseMissing('louos_quadro10_permissoes', ['id' => $perm->id]);
        $this->assertSame($contagem10, LouosQuadro10Permissao::query()->where('rule_version_id', $vigente10->id)->count());

        // --- Quadro 11A ---
        $vigente11a = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro11a,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro11a-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente11a->id, 'classe_via' => 'via_local', 'grupo_uso' => 'nR1']);
        LouosQuadro11CondicaoVia::factory()->create(['rule_version_id' => $vigente11a->id, 'classe_via' => 'via_coletora', 'grupo_uso' => 'nR1']);
        $contagem11a = 2;

        $draft11a = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro11a, '2026-q11a-rascunho', $user->id);
        $this->assertSame($contagem11a, LouosQuadro11CondicaoVia::query()->where('rule_version_id', $draft11a->id)->count());

        $cond = $this->service()->inserirLinha($draft11a, [
            'classe_via' => 'via_arterial',
            'grupo_uso' => 'nI1',
            'condicoes' => ['recuo_frontal_m' => 10],
        ]);
        $this->assertDatabaseHas('louos_quadro11_condicoes_via', ['rule_version_id' => $draft11a->id, 'classe_via' => 'via_arterial']);
        $this->assertSame($contagem11a, LouosQuadro11CondicaoVia::query()->where('rule_version_id', $vigente11a->id)->count());

        $this->service()->alterarLinha($draft11a, $cond->id, [
            'classe_via' => 'via_arterial',
            'grupo_uso' => 'nI1',
            'condicoes' => ['recuo_frontal_m' => 20],
        ]);
        $cond->refresh();
        $this->assertSame(['recuo_frontal_m' => 20], $cond->condicoes);

        $this->service()->excluirLinha($draft11a, $cond->id);
        $this->assertDatabaseMissing('louos_quadro11_condicoes_via', ['id' => $cond->id]);
        $this->assertSame($contagem11a, LouosQuadro11CondicaoVia::query()->where('rule_version_id', $vigente11a->id)->count());
    }

    public function test_operacao_fora_de_rascunho_e_rejeitada(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();

        $this->expectException(\DomainException::class);

        $this->service()->inserirLinha($vigente, [
            'cnae_code' => '9999-9/99',
            'area_min' => 0,
            'area_max' => 500,
            'grupo' => 'nR1',
        ]);
    }

    public function test_diff_conta_novas_alteradas_e_excluidas(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-diff-v1', $user->id);

        // Pegar as duas primeiras linhas do rascunho para alterar e excluir
        $linhas = LouosQuadro7Faixa::query()
            ->where('rule_version_id', $draft->id)
            ->orderBy('id')
            ->take(2)
            ->get();

        $linhaAlterar = $linhas->first();
        $linhaExcluir = $linhas->last();

        // Inserir uma nova (novas++)
        $this->service()->inserirLinha($draft, [
            'cnae_code' => '9999-9/99',
            'area_min' => 0,
            'area_max' => 500,
            'grupo' => 'nR1',
        ]);

        // Alterar uma existente (alteradas++) — mudar grupo não altera a chave natural
        $this->service()->alterarLinha($draft, $linhaAlterar->id, [
            'cnae_code' => $linhaAlterar->cnae_code,
            'area_min' => (float) $linhaAlterar->area_min,
            'area_max' => $linhaAlterar->area_max !== null ? (float) $linhaAlterar->area_max : null,
            'grupo' => $linhaAlterar->grupo.'_alterado',
            'subgrupo' => $linhaAlterar->subgrupo,
        ]);

        // Excluir uma existente (excluidas++)
        $this->service()->excluirLinha($draft, $linhaExcluir->id);

        $diff = $this->service()->diff($draft);

        $this->assertSame(1, $diff['novas']);
        $this->assertSame(1, $diff['alteradas']);
        $this->assertSame(1, $diff['excluidas']);
    }

    public function test_publicar_exige_quatro_olhos_e_promove_rascunho(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigenteAntiga = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemOriginal = LouosQuadro7Faixa::query()->where('rule_version_id', $vigenteAntiga->id)->count();

        $autor = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-pub-v1', $autor->id);

        // Excluir uma linha do rascunho
        $faixaExcluida = LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->first();
        $this->service()->excluirLinha($draft, $faixaExcluida->id);

        // Quatro olhos: autor igual ao publicador → exceção
        $this->expectException(FourEyesViolationException::class);
        $this->service()->publicar($draft, $autor->id);
    }

    public function test_publicar_com_publicador_distinto_promove_rascunho(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigenteAntiga = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemOriginal = LouosQuadro7Faixa::query()->where('rule_version_id', $vigenteAntiga->id)->count();

        $autor = User::factory()->create();
        $publicador = User::factory()->create();

        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-pub-v1', $autor->id);

        // Excluir uma linha do rascunho
        $faixaExcluida = LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->first();
        $this->service()->excluirLinha($draft, $faixaExcluida->id);

        $novaVigente = $this->service()->publicar($draft, $publicador->id);

        // Antiga se torna substituída
        $this->assertSame(RuleVersionStatus::Substituida, $vigenteAntiga->fresh()->status);

        // Draft promovido a vigente
        $this->assertSame(RuleVersionStatus::Vigente, $novaVigente->status);
        $this->assertSame($draft->id, $novaVigente->id);

        // Linha excluída não existe na nova vigente
        $this->assertSame(
            $contagemOriginal - 1,
            LouosQuadro7Faixa::query()->where('rule_version_id', $novaVigente->id)->count(),
        );
        $this->assertDatabaseMissing('louos_quadro7_faixas', ['id' => $faixaExcluida->id]);

        // Auditoria registra o evento de publicação
        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-publicado']);
    }

    public function test_descartar_remove_rascunho_e_preserva_vigente(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemVigente = LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count();

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-descartar-v1', $user->id);
        $draftId = $draft->id;

        $this->service()->descartar($draft);

        // Rascunho e suas linhas removidos
        $this->assertDatabaseMissing('rule_versions', ['id' => $draftId]);
        $this->assertSame(0, LouosQuadro7Faixa::query()->where('rule_version_id', $draftId)->count());

        // Vigente intacta
        $this->assertSame(RuleVersionStatus::Vigente, $vigente->fresh()->status);
        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());
    }

    public function test_importar_csv_no_rascunho_retorna_relatorio(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemVigente = LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count();

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-import-v1', $user->id);

        // CSV: 2 linhas válidas (mesmo CNAE, faixas contíguas) + 1 inválida (CNAE sem 7 dígitos)
        $csv = implode("\n", [
            'cnae,grupo,subgrupo,area_min,area_max,observacao',
            '9999-9/99,nR1,nR1-01,0,350,',
            '9999-9/99,nR2,,350,,',
            '9999-x/99,nR1,,0,350,',
        ]);
        $tmpFile = tempnam(sys_get_temp_dir(), 'louos_test_').'.csv';
        file_put_contents($tmpFile, $csv);

        try {
            $relatorio = $this->service()->importarCsv($draft, $tmpFile, 'planilha-quadro7.csv');

            $this->assertSame(2, $relatorio['importados']);
            $this->assertCount(1, $relatorio['rejeitados']);

            // Linhas caem no rascunho
            $this->assertSame(
                2,
                LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->where('cnae_code', '9999999')->count(),
            );

            // Vigente intacta: não tem CNAE 9999999 (CNAE sintético)
            $this->assertDatabaseMissing('louos_quadro7_faixas', ['rule_version_id' => $vigente->id, 'cnae_code' => '9999999']);
        } finally {
            unlink($tmpFile);
        }

        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());

        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-importacao']);

        $activity = Activity::query()
            ->where('log_name', 'louos')
            ->where('event', 'rascunho-importacao')
            ->latest()
            ->first();
        $this->assertSame('planilha-quadro7.csv', $activity->properties['arquivo']);
    }

    public function test_importar_csv_substituindo_apaga_linhas_anteriores_do_rascunho(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro7)->first();
        $contagemVigente = LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count();

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-import-replace-v1', $user->id);

        $this->assertGreaterThan(0, LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->count());

        $csv = implode("\n", [
            'cnae,grupo,subgrupo,area_min,area_max,observacao',
            '8888-8/88,nR1,nR1-01,0,350,carga oficial',
        ]);
        $tmpFile = tempnam(sys_get_temp_dir(), 'louos_replace_').'.csv';
        file_put_contents($tmpFile, $csv);

        try {
            $relatorio = $this->service()->importarCsv($draft, $tmpFile, 'oficial.csv', substituir: true);
        } finally {
            unlink($tmpFile);
        }

        $this->assertSame(1, $relatorio['importados']);
        $this->assertSame(1, LouosQuadro7Faixa::query()->where('rule_version_id', $draft->id)->count());
        $this->assertTrue(
            LouosQuadro7Faixa::query()
                ->where('rule_version_id', $draft->id)
                ->where('cnae_code', '8888888')
                ->exists(),
        );
        $this->assertSame($contagemVigente, LouosQuadro7Faixa::query()->where('rule_version_id', $vigente->id)->count());
    }

    public function test_normaliza_grupo_uso_nulo_no_quadro10(): void
    {
        $user = User::factory()->create();

        $vigente10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'status' => RuleVersionStatus::Vigente,
            'version' => 'quadro10-norm-v1',
            'valid_from' => now()->subYear()->toDateString(),
        ]);

        $draft10 = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro10, '2026-q10-norm', $user->id);

        // Insere linha com grupo_uso vazio (string) e subgrupo null
        $this->service()->inserirLinha($draft10, [
            'zona' => 'ZPR-1',
            'grupo_uso' => '',
            'subgrupo' => null,
            'permissao' => Quadro10Permissao::Proibido->value,
        ]);

        // Tenta inserir novamente com a mesma chave natural, mas grupo_uso chegando como null.
        // Sem o normalize de grupo_uso, naturalKey e existsByKey divergem:
        // naturalKey gera 'ZPR-1||' (null ?? ''), mas existsByKey faz WHERE grupo_uso IS NULL
        // e não encontra a linha anterior (que tem grupo_uso = ''), permitindo duplicata.
        // Com o fix, null é coercido para '' e a colisão é detectada corretamente.
        $this->expectException(ValidationException::class);

        $this->service()->inserirLinha($draft10, [
            'zona' => 'ZPR-1',
            'grupo_uso' => null,
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Permitido->value,
        ]);
    }

    public function test_auditoria_registra_mutacoes(): void
    {
        $this->seed(LouosQuadro7Seeder::class);

        $user = User::factory()->create();
        $draft = $this->service()->abrirOuRetomar(RuleDomain::LouosQuadro7, '2026-audit-v1', $user->id);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-aberto']);

        $linha = $this->service()->inserirLinha($draft, [
            'cnae_code' => '9999-9/99',
            'area_min' => 0,
            'area_max' => 500,
            'grupo' => 'nR1',
        ]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-linha-inserida']);

        $this->service()->alterarLinha($draft, $linha->id, [
            'cnae_code' => '9999-9/99',
            'area_min' => 0,
            'area_max' => 500,
            'grupo' => 'nR2',
        ]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-linha-alterada']);

        $this->service()->excluirLinha($draft, $linha->id);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-linha-excluida']);

        $this->service()->descartar($draft);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'louos', 'event' => 'rascunho-descartado']);
    }
}
