<?php

namespace Tests\Unit\Analise;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TllValor;
use App\Models\User;
use App\Services\Analise\TllPropagacaoExercicio;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Propagação anual da tabela TLL: clona as linhas ativas do exercício de
 * origem aplicando o fator do decreto, como rascunho versionado (domínio
 * tll_valores). Código inativo não propaga; destino vigente não regenera.
 */
class TllPropagacaoExercicioTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_propaga_linhas_ativas_aplicando_o_fator(): void
    {
        $autor = User::factory()->create();

        TllValor::factory()->create([
            'codigo_tll' => '2.02',
            'exercicio' => 2026,
            'valor' => '100.00',
            'taxa_servico' => '10.00',
            'codigo_tll_sefaz' => 'T45020425',
            'codigo_servico_sefaz' => 'S2253362',
            'servico_sefaz' => 'Inclusão de Atividade em Viabilidade MEI',
            'active' => true,
        ]);
        TllValor::factory()->create([
            'codigo_tll' => '9.99',
            'exercicio' => 2026,
            'valor' => '100.00',
            'active' => false,
        ]);

        $versao = app(TllPropagacaoExercicio::class)->propagar(
            2026,
            2027,
            '1.0446',
            'Decreto nº 41.304/2025',
            $autor->id,
        );

        $this->assertSame(RuleVersionStatus::Rascunho, $versao->status);
        $this->assertSame(RuleDomain::TllValores, $versao->domain);
        $this->assertSame('2027', $versao->version);
        $this->assertSame('Decreto nº 41.304/2025', $versao->source);
        $this->assertSame($autor->id, $versao->created_by);

        $novo = TllValor::query()->where('codigo_tll', '2.02')->where('exercicio', 2027)->sole();
        $this->assertSame('104.46', (string) $novo->valor);
        $this->assertSame('10.45', (string) $novo->taxa_servico);
        $this->assertSame('T45020425', $novo->codigo_tll_sefaz);
        $this->assertSame('S2253362', $novo->codigo_servico_sefaz);
        $this->assertTrue($novo->versao->is($versao));
        $this->assertDatabaseMissing('tll_valores', ['codigo_tll' => '9.99', 'exercicio' => 2027]);
    }

    public function test_origem_sem_linha_ativa_recusa(): void
    {
        $autor = User::factory()->create();
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'active' => false]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Nenhum valor ativo no exercício 2026 para propagar.');

        app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '1.0446', 'Decreto nº 41.304/2025', $autor->id);
    }

    public function test_fator_invalido_recusa(): void
    {
        $autor = User::factory()->create();
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'active' => true]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Fator de atualização inválido.');

        app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '2.5', 'Decreto nº 41.304/2025', $autor->id);
    }

    public function test_destino_vigente_nao_regera(): void
    {
        $autor = User::factory()->create();
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'valor' => '100.00', 'active' => true]);
        RuleVersion::factory()->create([
            'domain' => RuleDomain::TllValores,
            'version' => '2027',
            'status' => RuleVersionStatus::Vigente,
            'source' => 'Decreto anterior',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('O exercício 2027 já está publicado e não pode ser regerado.');

        app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '1.0446', 'Decreto nº 41.304/2025', $autor->id);
    }

    public function test_rascunho_regera_sem_duplicar_e_inativa_codigo_que_saiu(): void
    {
        $autor = User::factory()->create();
        TllValor::factory()->create(['codigo_tll' => '2.02', 'exercicio' => 2026, 'valor' => '100.00', 'taxa_servico' => '10.00', 'active' => true]);

        $primeiro = app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '1.0000', 'Decreto rascunho', $autor->id);
        TllValor::factory()->create(['codigo_tll' => '8.08', 'exercicio' => 2027, 'valor' => '50.00', 'rule_version_id' => $primeiro->id, 'active' => true]);

        $segundo = app(TllPropagacaoExercicio::class)->propagar(2026, 2027, '1.0446', 'Decreto nº 41.304/2025', $autor->id);

        $this->assertTrue($primeiro->is($segundo));
        $this->assertSame(1, RuleVersion::query()->where('domain', RuleDomain::TllValores->value)->where('version', '2027')->count());
        $this->assertSame(1, TllValor::query()->where('codigo_tll', '2.02')->where('exercicio', 2027)->count());

        $novo = TllValor::query()->where('codigo_tll', '2.02')->where('exercicio', 2027)->sole();
        $this->assertSame('104.46', (string) $novo->valor);

        $saiu = TllValor::query()->where('codigo_tll', '8.08')->where('exercicio', 2027)->sole();
        $this->assertFalse($saiu->active);
    }
}
