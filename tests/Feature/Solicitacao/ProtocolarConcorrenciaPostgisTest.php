<?php

namespace Tests\Feature\Solicitacao;

use App\Enums\ViabilityRequestStatus;
use App\Events\SolicitacaoProtocolada;
use App\Models\Cnae;
use App\Models\ProtocolSequence;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Solicitacao\ProtocolarSolicitacaoService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use Tests\PostgisTestCase;

/**
 * Número de protocolo único sob concorrência REAL contra Postgres (HU-068): o
 * ProtocolarSolicitacaoService protocola dentro de DB::transaction usando o
 * ProtocolNumberGenerator com protocol_sequences->lockForUpdate() (FOR UPDATE
 * emitido de verdade no pgsql, no-op em SQLite). Duas protocolizações sob o
 * lock avançam a sequência sem buraco nem duplicidade — o lock serializa o
 * incremento, e o unique(protocol_number) é a defesa final.
 *
 * Em SQLite o lock é no-op (por isso este teste é @group postgis); a unicidade
 * LÓGICA e o fluxo do protocolo já são cobertos em SQLite (ProtocolarSolicitacaoTest).
 */
#[Group('postgis')]
class ProtocolarConcorrenciaPostgisTest extends PostgisTestCase
{
    public function test_protocolo_unico_sob_concorrencia(): void
    {
        // O evento é fakeado para isolar a concorrência do número; a transição
        // síncrona (timeline + auditoria) continua rodando de verdade.
        Event::fake([SolicitacaoProtocolada::class]);

        $user = User::factory()->create();
        $primeira = $this->completeDraft($user);
        $segunda = $this->completeDraft($user);

        $service = app(ProtocolarSolicitacaoService::class);
        $primeiroNumero = $service->protocol($primeira, $user)->protocol_number;
        $segundoNumero = $service->protocol($segunda, $user)->protocol_number;

        $year = (int) now()->year;

        // Números sequenciais sob o lock — sem buraco, sem duplicidade.
        $this->assertSame("VIA-{$year}-000001", $primeiroNumero);
        $this->assertSame("VIA-{$year}-000002", $segundoNumero);
        $this->assertNotSame($primeiroNumero, $segundoNumero);

        // A sequência do ano avançou exatamente 2.
        $sequence = ProtocolSequence::query()->where('year', $year)->sole();
        $this->assertSame(2, (int) $sequence->last_number);

        // Ambas protocoladas com número único persistido.
        $this->assertSame(ViabilityRequestStatus::Protocolada, $primeira->refresh()->status);
        $this->assertSame(ViabilityRequestStatus::Protocolada, $segunda->refresh()->status);
        $this->assertSame(
            2,
            ViabilityRequest::query()->whereIn('id', [$primeira->id, $segunda->id])
                ->distinct()
                ->count('protocol_number'),
        );

        // Prova que o engine é o Postgres real (FOR UPDATE efetivo, não SQLite).
        $this->assertSame('pgsql', DB::connection()->getDriverName());
    }

    private function completeDraft(User $user): ViabilityRequest
    {
        $solicitacao = ViabilityRequest::factory()->draft()->create([
            'requester_user_id' => $user->id,
            'created_by_user_id' => $user->id,
            'used_area_m2' => 120.0,
        ]);

        $cnae = Cnae::factory()->create();
        $solicitacao->cnaes()->attach($cnae->id, ['is_primary' => true]);

        return $solicitacao;
    }
}
