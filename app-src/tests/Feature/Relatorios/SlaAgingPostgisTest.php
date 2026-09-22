<?php

namespace Tests\Feature\Relatorios;

use App\Enums\ViabilityRequestStatus;
use App\Models\Sector;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Relatorios\ReportFilters;
use App\Services\Relatorios\SlaVencimentosService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Evidência do ramo pgsql de SlaVencimentosService::progressoSql()
 * (EXTRACT(EPOCH) + CAST(? AS timestamp)). O default da suíte é sqlite
 * (strftime); este teste só conta se o servidor de teste abrir o socket.
 */
#[Group('postgis')]
class SlaAgingPostgisTest extends PostgisTestCase
{
    private Sector $setor;

    private User $analista;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        Carbon::setTestNow('2026-06-15 12:00:00');

        $this->setor = Sector::factory()->create(['name' => 'Setor Viabilidade']);
        $this->analista = User::factory()->analista()->withAcceptedLgpdTerm()->create(['name' => 'Ana Analista']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_aging_classifica_faixas_e_indeterminada_no_pgsql(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());

        $agora = Carbon::parse('2026-06-15 12:00:00');

        // 25% do prazo (0_50): started 4d atrás, due daqui a 12d.
        $this->emAndamento('VIA-2026-A0001', $agora->copy()->addDays(12)->toDateTimeString());
        ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0001')
            ->update(['analysis_stage_started_at' => $agora->copy()->subDays(4)]);

        // 60% (50_80)
        $this->emAndamento('VIA-2026-A0002', $agora->copy()->addDays(4)->toDateTimeString());
        ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0002')
            ->update(['analysis_stage_started_at' => $agora->copy()->subDays(6)]);

        // 90% (80_100)
        $this->emAndamento('VIA-2026-A0003', $agora->copy()->addDay()->toDateTimeString());
        ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0003')
            ->update(['analysis_stage_started_at' => $agora->copy()->subDays(9)]);

        // >100%
        $this->emAndamento('VIA-2026-A0004', '2026-06-14 12:00');

        // indeterminada: sem started_at
        $this->emAndamento('VIA-2026-A0005', $agora->copy()->addDays(5)->toDateTimeString());
        ViabilityRequest::query()->where('protocol_number', 'VIA-2026-A0005')
            ->update(['analysis_stage_started_at' => null]);

        $aging = collect(app(SlaVencimentosService::class)->aging(ReportFilters::fromArray([])))
            ->keyBy('faixa');

        $this->assertSame(1, $aging['0_50']['total']);
        $this->assertSame(1, $aging['50_80']['total']);
        $this->assertSame(1, $aging['80_100']['total']);
        $this->assertSame(1, $aging['acima_100']['total']);
        $this->assertSame(1, $aging['indeterminada']['total']);
    }

    private function emAndamento(string $protocolo, string $dueAt): ViabilityRequest
    {
        return ViabilityRequest::factory()->create([
            'status' => ViabilityRequestStatus::EmAnalise,
            'protocol_number' => $protocolo,
            'protocoled_at' => Carbon::parse($dueAt)->subDays(5),
            'analysis_stage' => 'analise',
            'analysis_stage_started_at' => Carbon::parse($dueAt)->subDays(5),
            'analysis_due_at' => Carbon::parse($dueAt),
            'sector_id' => $this->setor->id,
            'assigned_user_id' => $this->analista->id,
        ]);
    }
}
