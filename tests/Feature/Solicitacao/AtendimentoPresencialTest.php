<?php

namespace Tests\Feature\Solicitacao;

use App\Models\AssistedAttendance;
use App\Models\Procuration;
use App\Models\User;
use App\Support\Representation\CurrentRepresentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Atendimento presencial assistido (HU-150): o atendente autorizado opera o
 * sistema "em nome de" o cidadão presente no balcão (inclusão digital),
 * REUSANDO o mecanismo de representação da Fase 1 — modelo leve
 * AssistedAttendance + middleware análogo populando o MESMO
 * CurrentRepresentation/Context. Toda ação registra ator (atendente) e
 * beneficiário (cidadão); o vínculo expira e exige reabertura; o escopo é
 * limitado por permissão (RN-001/RN-002, CA-01..CA-04).
 */
class AtendimentoPresencialTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_escopo_active_ignora_expirado_e_encerrado(): void
    {
        $active = AssistedAttendance::factory()->active()->create();
        $expired = AssistedAttendance::factory()->expired()->create();
        $ended = AssistedAttendance::factory()->ended()->create();

        $activeIds = AssistedAttendance::active()->pluck('id');

        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($expired->id));
        $this->assertFalse($activeIds->contains($ended->id));

        $this->assertTrue($active->isActive());
        $this->assertFalse($expired->isActive());
        $this->assertFalse($ended->isActive());
    }

    public function test_representacao_resolve_o_cidadao_atendido(): void
    {
        $citizen = User::factory()->create();
        $attendance = AssistedAttendance::factory()->active()->create([
            'citizen_user_id' => $citizen->id,
        ]);

        $representation = app(CurrentRepresentation::class);
        $representation->setAttendance($attendance);

        $this->assertSame($citizen->id, $representation->grantor()?->id);
        $this->assertSame($attendance->id, $representation->attendance()?->id);

        $representation->clearAttendance();
        $this->assertNull($representation->grantor());
        $this->assertNull($representation->attendance());
    }

    public function test_procuracao_continua_resolvendo_o_outorgante(): void
    {
        $grantor = User::factory()->create();
        $procuration = Procuration::factory()->create(['grantor_user_id' => $grantor->id]);

        $representation = app(CurrentRepresentation::class);
        $representation->set($procuration);

        // O caminho de procuração da Fase 1 NÃO pode regredir ao reusar o
        // CurrentRepresentation para o atendimento presencial.
        $this->assertSame($grantor->id, $representation->grantor()?->id);
    }
}
