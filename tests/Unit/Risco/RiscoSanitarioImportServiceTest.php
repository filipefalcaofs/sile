<?php

namespace Tests\Unit\Risco;

use App\Enums\RiscoSanitario;
use App\Enums\RuleDomain;
use App\Models\RiskCondicionante;
use App\Models\RuleVersion;
use App\Models\SanitaryRiskClassification;
use App\Services\Risco\RiscoSanitarioImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Dimensão SANITÁRIA de risco (HU-019/HU-047/HU-048) a partir do dado oficial
 * real (planilha unificada CNAE da Vigilância Sanitária). A VISA tem três
 * níveis próprios — Baixo, Médio e Alto — DISTINTOS dos níveis municipais
 * (baixo_a/baixo_b/alto): aqui existe "médio". fromVisa() mapeia os rótulos da
 * planilha e rejeita qualquer nível desconhecido (nunca inventa classificação).
 * O import é provado sobre o CSV REAL commitado em database/data/risco/ — as
 * contagens (285→261, 83/120/58, 67 condicionantes) são assertadas sobre o
 * dado oficial, sem fixture sintético, e a condicionante-pergunta reclassifica
 * o risco conforme o texto da planilha (golden case 1031-7/00 → Alto Risco).
 */
class RiscoSanitarioImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): RiscoSanitarioImportService
    {
        return app(RiscoSanitarioImportService::class);
    }

    private function csvOficial(): string
    {
        return database_path('data/risco/planilha-unificada-cnae-30-04-26.csv');
    }

    private function versaoSanitaria(): RuleVersion
    {
        return RuleVersion::factory()->create([
            'domain' => RuleDomain::RiscoSanitario,
            'version' => 'visa-unificada-2026-04-30',
            'rules_version' => 'visa-unificada-2026-04-30',
        ]);
    }

    public function test_enum_from_visa_mapeia_niveis(): void
    {
        $this->assertSame(RiscoSanitario::Baixo, RiscoSanitario::fromVisa('Baixo'));
        $this->assertSame(RiscoSanitario::Medio, RiscoSanitario::fromVisa('Médio'));
        $this->assertSame(RiscoSanitario::Alto, RiscoSanitario::fromVisa('Alto'));

        // Tolera ruído de formatação da planilha oficial (espaços/caixa/acento).
        $this->assertSame(RiscoSanitario::Medio, RiscoSanitario::fromVisa('  MÉDIO '));
        $this->assertSame(RiscoSanitario::Baixo, RiscoSanitario::fromVisa('baixo'));

        // Níveis sanitários são distintos dos municipais (existe "médio").
        $this->assertSame('medio', RiscoSanitario::Medio->value);

        // Nível desconhecido lança exceção, nunca é inventado.
        $this->expectException(InvalidArgumentException::class);
        RiscoSanitario::fromVisa('Altíssimo');
    }

    public function test_import_carrega_classificacoes_sanitarias(): void
    {
        $version = $this->versaoSanitaria();

        $report = $this->service()->import($version, $this->csvOficial());

        // Planilha VISA real: 285 linhas → 261 subclasses CNAE (24 linhas são
        // sub-atividades da mesma subclasse, colapsadas pelo nível mais
        // restritivo). Distribuição final medida sobre o dado oficial.
        $this->assertSame(285, $report['lidos']);
        $this->assertSame(261, $report['classificacoes']);
        $this->assertSame(
            ['baixo' => 83, 'medio' => 120, 'alto' => 58],
            $report['por_nivel'],
        );
        $this->assertSame([], $report['rejeitados']);

        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(83, SanitaryRiskClassification::query()->where('risco_sanitario', 'baixo')->count());
        $this->assertSame(120, SanitaryRiskClassification::query()->where('risco_sanitario', 'medio')->count());
        $this->assertSame(58, SanitaryRiskClassification::query()->where('risco_sanitario', 'alto')->count());

        // 67 condicionantes-pergunta reais da planilha.
        $this->assertSame(67, $report['condicionantes']);
        $this->assertSame(67, RiskCondicionante::query()->count());
    }

    public function test_import_cria_condicionante_pergunta_para_1031(): void
    {
        $version = $this->versaoSanitaria();

        $this->service()->import($version, $this->csvOficial());

        // Golden case VISA: 1031-7/00 (fabricação de conservas de frutas) nasce
        // Baixo, mas a condicionante-pergunta reclassifica para Alto quando o
        // produto não é artesanal (mecanismo "DI").
        $classificacao = SanitaryRiskClassification::query()
            ->where('rule_version_id', $version->id)
            ->where('cnae_code', '1031700')
            ->sole();

        $this->assertSame(RiscoSanitario::Baixo, $classificacao->risco_sanitario);

        $condicionantes = RiskCondicionante::query()
            ->where('rule_version_id', $version->id)
            ->where('cnae_code', '1031700')
            ->get();

        $this->assertCount(1, $condicionantes);

        $condicionante = $condicionantes->sole();
        $this->assertStringContainsString('artesanal', $condicionante->pergunta);
        $this->assertSame('booleano_sim_nao', $condicionante->tipo_resposta->value);

        $regra = $condicionante->regra_reclassificacao;
        $this->assertTrue($regra['resposta_gatilho']);
        $this->assertSame('alto', $regra['reclassifica_para']);
        $this->assertStringContainsString('Alto Risco', $regra['fundamento']);
    }

    public function test_import_reclassifica_para_medio_quando_a_planilha_indica(): void
    {
        $version = $this->versaoSanitaria();

        $this->service()->import($version, $this->csvOficial());

        // 8112-5/00: a condicionante diz "Caso haja ... será considerado Médio
        // Risco" — o nível-alvo é DERIVADO do texto, nunca fixado em 'alto'.
        $condicionante = RiskCondicionante::query()
            ->where('rule_version_id', $version->id)
            ->where('cnae_code', '8112500')
            ->sole();

        $this->assertSame('medio', $condicionante->regra_reclassificacao['reclassifica_para']);
    }

    public function test_import_mantem_nivel_mais_restritivo_em_cnae_duplicado(): void
    {
        $version = $this->versaoSanitaria();

        $report = $this->service()->import($version, $this->csvOficial());

        // 8129-0/00 aparece na planilha com Médio (2x) e Alto (1x). Risco
        // sanitário nunca é rebaixado silenciosamente: prevalece o mais
        // restritivo (Alto) e a divergência é registrada como aviso auditável.
        $classificacao = SanitaryRiskClassification::query()
            ->where('rule_version_id', $version->id)
            ->where('cnae_code', '8129000')
            ->sole();

        $this->assertSame(RiscoSanitario::Alto, $classificacao->risco_sanitario);

        $this->assertNotEmpty($report['avisos']);
        $this->assertStringContainsString('8129000', implode(' ', $report['avisos']));
    }

    public function test_import_e_idempotente(): void
    {
        $version = $this->versaoSanitaria();

        $this->service()->import($version, $this->csvOficial());
        $segundo = $this->service()->import($version, $this->csvOficial());

        // Re-import não duplica classificações (upsert) nem condicionantes
        // (firstOrCreate por rule_version_id + cnae_code + pergunta).
        $this->assertSame(261, SanitaryRiskClassification::query()->count());
        $this->assertSame(67, RiskCondicionante::query()->count());
        $this->assertSame(261, $segundo['classificacoes']);
        $this->assertSame(67, $segundo['condicionantes']);
    }
}
