<?php

namespace Tests\Feature\Relatorios;

use App\Models\Holiday;
use Database\Seeders\HolidaySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cadastro de feriados (HU-137): dado versionado/auditado e substituível. O
 * seeder semeia APENAS os feriados nacionais fixos inquestionáveis como dado de
 * partida — a lista municipal oficial de Salvador é pendência SEDUR (degradação
 * honesta). Nenhum feriado municipal é inventado.
 */
class HolidayCadastroTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_de_feriados_e_idempotente(): void
    {
        $this->seed(HolidaySeeder::class);
        $primeiraContagem = Holiday::query()->count();

        // Rodar o seeder novamente NÃO duplica os feriados (updateOrCreate por data).
        $this->seed(HolidaySeeder::class);

        $this->assertGreaterThan(0, $primeiraContagem);
        $this->assertSame($primeiraContagem, Holiday::query()->count());
    }

    public function test_data_do_feriado_e_unica(): void
    {
        Holiday::factory()->create(['date' => '2026-12-25']);

        $this->expectException(QueryException::class);

        Holiday::factory()->create(['date' => '2026-12-25']);
    }

    public function test_criacao_de_feriado_e_auditada(): void
    {
        // RN-002: o cadastro de feriado é dado versionado/auditado (HasAuditoria).
        $holiday = Holiday::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $holiday->getMorphClass(),
            'subject_id' => $holiday->id,
            'event' => 'created',
        ]);
    }

    public function test_edicao_de_feriado_e_auditada(): void
    {
        $holiday = Holiday::factory()->create(['name' => 'Natal']);

        $holiday->update(['name' => 'Natal (revisado)']);

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $holiday->getMorphClass(),
            'subject_id' => $holiday->id,
            'event' => 'updated',
        ]);
    }

    public function test_seeder_marca_feriados_nacionais_como_recorrentes(): void
    {
        $this->seed(HolidaySeeder::class);

        // Natal (25/12) é feriado nacional fixo — recorrente anualmente.
        $natal = Holiday::query()->whereMonth('date', 12)->whereDay('date', 25)->first();

        $this->assertNotNull($natal);
        $this->assertTrue($natal->recurring_annually);
        $this->assertTrue($natal->active);
    }
}
