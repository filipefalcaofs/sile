<?php

namespace Tests\Feature\Decisao;

use App\Enums\Quadro10Permissao;
use App\Enums\ResultadoViabilidade;
use App\Enums\RuleDomain;
use App\Models\DecisionText;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\User;
use App\Services\Geo\TerritoryResult;
use App\Services\Louos\EnquadramentoInput;
use App\Services\Louos\EnquadramentoResult;
use App\Services\Louos\LouosEnquadramentoService;
use Database\Seeders\DecisionTextSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\SeedsTratamentoPlanilha;
use Tests\TestCase;

/**
 * Manutenção dos textos decisórios (Fase 4, Task 5): o administrador edita
 * template/descrição dos textos emitidos em documentos oficiais (TVL,
 * parecer, ficha do cidadão) pela retaguarda, atrás da permissão
 * manter-parametros (reuso — mesmo mantenedor dos textos-padrão). SEM
 * create/delete/toggle: a chave é ligada ao motor (call site no código) —
 * criar chave sem call site seria fachada e apagar quebraria a emissão.
 * Toda edição é auditada (RN-002 via HasAuditoria) e tem efeito sem deploy
 * (cache do catálogo invalidado na escrita do model). Espelha o
 * RiskTriggerCrudTest; a prova de ponta a ponta exercita o motor real de
 * enquadramento LOUOS (mesmo caminho do LouosTextosByteIdenticosTest).
 */
class DecisionTextCrudTest extends TestCase
{
    use LazilyRefreshDatabase;
    use SeedsTratamentoPlanilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    public function test_lista_exige_permissao(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-parametros.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/textos-decisao')
            ->assertForbidden();
    }

    public function test_lista_agrupa_os_textos_por_prefixo_da_chave(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/textos-decisao')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/textos-decisao/index')
                ->has('grupos', 6)
                ->has('grupos.0', fn (Assert $grupo) => $grupo
                    ->where('prefixo', 'base_legal')
                    ->has('label')
                    ->has('textos', 2)
                    ->has('textos.0', fn (Assert $texto) => $texto
                        ->where('key', 'base_legal.louos')
                        ->has('template')
                        ->has('description')
                    )
                )
            );
    }

    /**
     * Prova de ponta a ponta: editar `louos.motivo.proibido` pela tela muda o
     * que o motor emite — o enquadramento não permitido passa a consolidar o
     * motivo com o texto novo (cache invalidado na escrita, sem deploy).
     */
    public function test_edicao_do_template_persiste_e_o_motor_emite_o_novo_texto(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $texto = DecisionText::query()->where('key', 'louos.motivo.proibido')->firstOrFail();

        $this->actingAs($this->administrador(), 'gestao')
            ->put("/gestao/textos-decisao/{$texto->key}", [
                'template' => 'Uso vedado na zona pelo Quadro 10 da LOUOS',
                'description' => $texto->description,
            ])->assertRedirect();

        $texto->refresh();

        $this->assertSame('Uso vedado na zona pelo Quadro 10 da LOUOS', $texto->template);
        $this->assertDatabaseHas('activity_log', [
            'subject_type' => DecisionText::class,
            'subject_id' => $texto->id,
            'event' => 'updated',
        ]);

        $result = $this->enquadrarNaoPermitido();

        $this->assertSame(ResultadoViabilidade::NaoPermitido->value, $result->resultado());
        $this->assertSame(
            'Não permitido: o CNAE 4712-1/00 (área 100 m²) classificou-se no grupo nR1 pelo enquadramento da planilha vigente e esse grupo é proibido na zona ZPAM pelo Quadro 10. Uso vedado na zona pelo Quadro 10 da LOUOS.',
            $result->consolidado['motivo'],
        );
    }

    public function test_edicao_de_chave_inexistente_retorna_404(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $this->actingAs($this->administrador(), 'gestao')
            ->put('/gestao/textos-decisao/chave.que.nao.existe', [
                'template' => 'Texto inventado',
                'description' => 'Chave sem call site no motor.',
            ])->assertNotFound();
    }

    /**
     * Anti-fachada: a chave é ligada ao motor — uma linha criada pela UI sem
     * call site não faria nada, e apagar uma chave quebraria a emissão do
     * documento. Por isso NÃO existem rotas de criação nem de exclusão.
     */
    public function test_nao_existe_rota_de_criacao_ou_exclusao(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $admin = $this->administrador();

        $this->actingAs($admin, 'gestao')
            ->post('/gestao/textos-decisao', [
                'key' => 'louos.motivo.inventado',
                'template' => 'Texto sem call site',
                'description' => 'Chave inventada pela tela.',
            ])->assertMethodNotAllowed();

        $this->actingAs($admin, 'gestao')
            ->delete('/gestao/textos-decisao/louos.motivo.proibido')
            ->assertMethodNotAllowed();
    }

    public function test_template_e_obrigatorio_na_edicao(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $this->actingAs($this->administrador(), 'gestao')
            ->put('/gestao/textos-decisao/louos.motivo.proibido', [
                'template' => '',
                'description' => 'Descrição válida',
            ])->assertSessionHasErrors('template');
    }

    /**
     * Cenário não permitido do motor LOUOS: a planilha enquadra o CNAE no
     * grupo nR1 e o Quadro 10 proíbe o grupo na zona ZPAM.
     */
    private function enquadrarNaoPermitido(): EnquadramentoResult
    {
        $this->seedTratamentoPlanilha();

        $quadro10 = RuleVersion::factory()->create([
            'domain' => RuleDomain::LouosQuadro10,
            'version' => 'lei-9148-2016-quadro10',
            'rules_version' => 'lei-9148-2016-quadro10',
        ]);

        LouosQuadro10Permissao::factory()->create([
            'rule_version_id' => $quadro10->id,
            'zona' => 'ZPAM',
            'grupo_uso' => 'nR1',
            'subgrupo' => '',
            'permissao' => Quadro10Permissao::Proibido,
            'condicionante_ref' => null,
            'base_legal' => null,
        ]);

        $dimVazia = [
            'status' => 'nao_encontrado',
            'nome' => null,
            'propriedades' => null,
            'motivo' => null,
            'versao_camada' => null,
        ];

        $territorio = new TerritoryResult(
            bairro: $dimVazia,
            via: $dimVazia + ['distancia_m' => null],
            zona: [
                'status' => 'identificado',
                'nome' => 'ZPAM',
                'propriedades' => ['NOME' => 'ZPAM'],
                'motivo' => null,
                'versao_camada' => 'zoneamento-louos-2026',
            ],
            lote: $dimVazia,
            restricoes: ['status' => 'nao_encontrado', 'itens' => [], 'motivo' => null, 'versao_camada' => null],
        );

        return app(LouosEnquadramentoService::class)->enquadrar(
            EnquadramentoInput::paraConsulta(100, '4712-1/00', $territorio, [11 => true]),
        );
    }
}
