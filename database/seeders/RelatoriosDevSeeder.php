<?php

namespace Database\Seeders;

use App\Enums\ViabilityRequestStatus;
use App\Models\Company;
use App\Models\ExpressoQueda;
use App\Models\User;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestTransition;
use App\Models\ViabilityServiceType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Massa de DEV dos relatórios e indicadores (EP15): solicitações em estados
 * VARIADOS — protocoladas, em análise, em pendência, deferidas, indeferidas e
 * canceladas — com decisões, transições (timeline) e quedas do fluxo expresso,
 * geradas pelas FACTORIES do fluxo real. As telas/indicadores CALCULAM sobre
 * esses dados (KPIs, tempo de análise, produtividade, quedas) — NUNCA números
 * cravados na tela (entrega-funcional / anti-fachada).
 *
 * Gate de ambiente: SÓ em `local` (não em `testing`, de propósito). Diferente dos
 * demais seeders dev, a massa de indicadores fica fora do teste porque a suíte
 * semeia por caso (RefreshDatabase) e o golden/smoke do export semeia seu próprio
 * conjunto conhecido — somar esta massa quebraria as contagens exatas asseridas
 * pelo DatabaseSeederTest. Em `local`, popula o dashboard e as telas para o smoke
 * navegável e a evidência do comando relatorios:exportar.
 *
 * Idempotente: requerente dedicado (relatorios-demo@sile.dev); se já houver
 * solicitações dele, o seed está completo e nada é duplicado.
 */
class RelatoriosDevSeeder extends Seeder
{
    public const REQUESTER_EMAIL = 'relatorios-demo@sile.dev';

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): massa de indicadores só em local.
        if (! app()->environment('local')) {
            $this->command?->warn('RelatoriosDevSeeder: ignorado fora de local (massa de indicadores de dev).');

            return;
        }

        $requester = User::query()->where('email', self::REQUESTER_EMAIL)->first();

        // Idempotência: solicitações do requerente dedicado já existem → semeado.
        if ($requester !== null && ViabilityRequest::query()->where('requester_user_id', $requester->id)->exists()) {
            return;
        }

        $requester ??= User::factory()->create([
            'email' => self::REQUESTER_EMAIL,
            'name' => 'Requerente Demo Relatórios',
        ]);

        $company = Company::factory()->create(['legal_name' => 'Empresa Demo Relatórios LTDA']);

        $serviceType = ViabilityServiceType::query()->where('active', true)->orderBy('id')->first()
            ?? ViabilityServiceType::factory()->create();

        $analista = User::query()->where('email', 'analista@sile.dev')->first()
            ?? User::factory()->create(['name' => 'Analista Demo Relatórios']);

        // Em andamento — protocoladas (distribuídas no tempo p/ indicadores temporais).
        for ($i = 0; $i < 4; $i++) {
            $req = $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::Protocolada, now()->subDays(20 - $i));
            $this->transicao($req, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, $req->protocoled_at);
        }

        // Em análise — atribuídas ao analista, com prazo (SLA da fila).
        for ($i = 0; $i < 3; $i++) {
            $req = $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::EmAnalise, now()->subDays(12 - $i), [
                'assigned_user_id' => $analista->id,
                'assigned_at' => now()->subDays(11 - $i),
                'analysis_due_at' => now()->addDays(5),
            ]);
            $this->transicao($req, ViabilityRequestStatus::Rascunho, ViabilityRequestStatus::Protocolada, $req->protocoled_at);
            $this->transicao($req, ViabilityRequestStatus::Protocolada, ViabilityRequestStatus::EmAnalise, now()->subDays(11 - $i));
        }

        // Em pendência.
        for ($i = 0; $i < 2; $i++) {
            $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::EmPendencia, now()->subDays(9 - $i), [
                'assigned_user_id' => $analista->id,
            ]);
        }

        // Deferidas — decisão deferida (TVL único) + timeline até o desfecho.
        for ($i = 0; $i < 5; $i++) {
            $req = $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::Deferida, now()->subDays(30 - $i));
            ViabilityDecision::factory()->create([
                'viability_request_id' => $req->id,
                'tvl_product_number' => fake()->unique()->numerify('TVL-'.now()->year.'-######'),
                'decided_at' => now()->subDays(25 - $i),
            ]);
            $this->transicao($req, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Deferida, now()->subDays(25 - $i));
        }

        // Indeferidas — decisão indeferida (sem TVL) + timeline até o desfecho.
        for ($i = 0; $i < 3; $i++) {
            $req = $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::Indeferida, now()->subDays(28 - $i));
            ViabilityDecision::factory()->indeferida()->create([
                'viability_request_id' => $req->id,
                'decided_at' => now()->subDays(23 - $i),
            ]);
            $this->transicao($req, ViabilityRequestStatus::EmAnalise, ViabilityRequestStatus::Indeferida, now()->subDays(23 - $i));
        }

        // Canceladas.
        for ($i = 0; $i < 2; $i++) {
            $this->solicitacao($requester, $company, $serviceType, ViabilityRequestStatus::Cancelada, now()->subDays(15 - $i), [
                'cancelled_at' => now()->subDays(14 - $i),
                'cancelled_reason' => 'Cancelada pelo requerente (massa de dev).',
            ]);
        }

        // Quedas do fluxo expresso (HU-145): classificadas por gatilho e degradadas
        // (nível-processo, sem gatilho — RN-001, queda honesta nunca inventada).
        ExpressoQueda::factory()->count(3)->create();
        ExpressoQueda::factory()->nivelProcesso()->count(2)->create();
    }

    /**
     * Cria uma solicitação do requerente/empresa dedicados no status pedido, com
     * protocolo único (exceto rascunho) e bairro de Salvador.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function solicitacao(
        User $requester,
        Company $company,
        ViabilityServiceType $serviceType,
        ViabilityRequestStatus $status,
        Carbon $protocoledAt,
        array $overrides = [],
    ): ViabilityRequest {
        return ViabilityRequest::factory()->create(array_merge([
            'status' => $status,
            'service_type_id' => $serviceType->id,
            'company_id' => $company->id,
            'requester_user_id' => $requester->id,
            'created_by_user_id' => $requester->id,
            'protocol_number' => fake()->unique()->numerify('VIA-'.now()->year.'-######'),
            'protocoled_at' => $protocoledAt,
            'address_neighborhood' => fake()->randomElement(['Pituba', 'Itapuã', 'Brotas', 'Barra', 'Cabula', 'Liberdade']),
        ], $overrides));
    }

    private function transicao(
        ViabilityRequest $request,
        ViabilityRequestStatus $from,
        ViabilityRequestStatus $to,
        ?Carbon $at,
    ): void {
        ViabilityRequestTransition::factory()->create([
            'viability_request_id' => $request->id,
            'from_status' => $from,
            'to_status' => $to,
            'actor_user_id' => null,
            'created_at' => $at ?? now(),
            'updated_at' => $at ?? now(),
        ]);
    }
}
