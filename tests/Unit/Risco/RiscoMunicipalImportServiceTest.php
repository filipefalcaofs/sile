<?php

namespace Tests\Unit\Risco;

use App\Enums\RiscoMunicipal;
use App\Models\RiskClassification;
use App\Models\RuleVersion;
use App\Services\Risco\RiscoMunicipalImportService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Dimensão municipal de risco (HU-020/HU-047) a partir do dado oficial real
 * (Decreto nº 32.636/2020). O Decreto só tem três níveis — NÃO existe "médio"
 * (regra firme do analista-negocio). fromDecreto() mapeia os rótulos do CSV e
 * rejeita qualquer nível desconhecido (nunca inventa classificação). O import
 * é provado sobre o CSV REAL commitado em database/data/risco/ — a contagem
 * 767/328/236 é assertada sobre o dado oficial, sem fixture sintético.
 */
class RiscoMunicipalImportServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): RiscoMunicipalImportService
    {
        return app(RiscoMunicipalImportService::class);
    }

    private function csvOficial(): string
    {
        return database_path('data/risco/decreto-32636-2020-risco-municipal-unificado-cnae.csv');
    }

    public function test_enum_from_decreto_mapeia_rotulos_oficiais(): void
    {
        $this->assertSame(RiscoMunicipal::BaixoA, RiscoMunicipal::fromDecreto('BAIXO A'));
        $this->assertSame(RiscoMunicipal::BaixoB, RiscoMunicipal::fromDecreto('BAIXO B'));
        $this->assertSame(RiscoMunicipal::Alto, RiscoMunicipal::fromDecreto('ALTO'));

        // Tolera ruído de formatação da planilha oficial (espaços/caixa).
        $this->assertSame(RiscoMunicipal::BaixoA, RiscoMunicipal::fromDecreto('  baixo a '));

        // "MÉDIO" não existe no Decreto — nível desconhecido lança exceção,
        // nunca é inventado.
        $this->expectException(InvalidArgumentException::class);
        RiscoMunicipal::fromDecreto('MÉDIO');
    }

    public function test_enum_exibe_nomenclatura_sedur_baixo_medio_alto(): void
    {
        $this->assertSame('Baixo', RiscoMunicipal::BaixoA->label());
        $this->assertSame('Médio', RiscoMunicipal::BaixoB->label());
        $this->assertSame('Alto', RiscoMunicipal::Alto->label());

        foreach (RiscoMunicipal::cases() as $nivel) {
            $this->assertStringNotContainsString('Risco A', $nivel->label());
            $this->assertStringNotContainsString('Risco B', $nivel->label());
        }
    }

    public function test_import_carrega_todas_as_classificacoes_do_decreto(): void
    {
        $version = RuleVersion::factory()->create();

        $report = $this->service()->import($version, $this->csvOficial());

        // Distribuição oficial do Decreto nº 32.636/2020 (dado real).
        $this->assertSame(1331, $report['total']);
        $this->assertSame(
            ['baixo_a' => 767, 'baixo_b' => 328, 'alto' => 236],
            $report['por_nivel'],
        );
        $this->assertSame([], $report['rejeitados']);
        $this->assertSame(1331, $report['importados']);

        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(767, RiskClassification::query()->where('risco_municipal', 'baixo_a')->count());
        $this->assertSame(328, RiskClassification::query()->where('risco_municipal', 'baixo_b')->count());
        $this->assertSame(236, RiskClassification::query()->where('risco_municipal', 'alto')->count());
    }

    public function test_import_parseia_condicionantes_gerais(): void
    {
        $version = RuleVersion::factory()->create();

        $this->service()->import($version, $this->csvOficial());

        $classificacao = RiskClassification::query()
            ->where('rule_version_id', $version->id)
            ->where('cnae_code', '0111301')
            ->sole();

        $condicionantes = $classificacao->condicionantes;

        $this->assertCount(3, $condicionantes);
        // Marcador de lista '- ' removido; sem perda de conteúdo.
        $this->assertStringContainsString('escritório', $condicionantes[0]);
        $this->assertStringStartsNotWith('-', $condicionantes[0]);
        $this->assertStringContainsString('residencial', $condicionantes[1]);
        $this->assertStringContainsString('1.250m²', $condicionantes[2]);
    }

    public function test_import_rejeita_nivel_desconhecido(): void
    {
        $version = RuleVersion::factory()->create();

        $report = $this->service()->import(
            $version,
            base_path('tests/Fixtures/risco/decreto-com-nivel-invalido.csv'),
        );

        // A linha válida (BAIXO A) entra; a 'MÉDIO' é rejeitada, nunca inventada.
        $this->assertSame(1, $report['total']);
        $this->assertCount(1, $report['rejeitados']);
        $this->assertStringContainsString('MÉDIO', $report['rejeitados'][0]);

        $this->assertSame(1, RiskClassification::query()->count());
        $this->assertSame(
            0,
            RiskClassification::query()->where('cnae_code', '9999999')->count(),
        );
    }

    public function test_import_e_idempotente(): void
    {
        $version = RuleVersion::factory()->create();

        $this->service()->import($version, $this->csvOficial());
        $segundo = $this->service()->import($version, $this->csvOficial());

        // Re-import não duplica (upsert por rule_version_id + cnae_code).
        $this->assertSame(1331, RiskClassification::query()->count());
        $this->assertSame(1331, $segundo['total']);
        $this->assertSame(1331, $segundo['atualizados']);
        $this->assertSame(0, $segundo['importados']);
    }
}
