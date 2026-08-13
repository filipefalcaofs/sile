<?php

namespace Tests\Feature\Seeders;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Models\AbuseAlert;
use App\Models\Activity;
use App\Models\Company;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Support\Settings;
use Database\Seeders\AuditoriaDevSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AuditoriaDevSeeder (fechamento do EP12): o ambiente de desenvolvimento ganha
 * dados de auditoria/abuso/LGPD/explicabilidade REAIS — produzidos pela LÓGICA de
 * verdade, não linhas fabricadas. O seeder semeia um PADRÃO DEDICADO de abuso (N
 * solicitações do mesmo CNPJ por um requerente/empresa próprios), habilita o
 * toggle features.deteccao_abuso SÓ durante o seed e roda o AbuseDetectionService
 * REAL (gera abuse_alerts e, acima do limiar, 1 encaminhamento à malha fina pelo
 * caminho de sistema), restaurando o toggle para OFF ao final; produz ≥1 acesso a
 * dado pessoal pelo AuditService (personal_data=true) e leva um processo dedicado
 * à decisão pela análise técnica (ViabilityDecision com decision_trace), além de
 * uma decisão legada SEM trace (sub-passo "não registrado" da HU-099).
 *
 * Asserções de PRESENÇA (não contagem global frágil): provam que o dev navegável
 * nasce do fluxo real e é idempotente. Roda em SQLite (RefreshDatabase) — a
 * detecção e a decisão da análise técnica são territorial-agnósticas.
 */
class AuditoriaDevSeederTest extends TestCase
{
    use RefreshDatabase;

    private function empresaAbuso(): ?Company
    {
        return Company::query()->where('cnpj', AuditoriaDevSeeder::CNPJ_ABUSO)->first();
    }

    public function test_seed_gera_alerta_de_abuso_real_com_encaminhamento_a_malha_fina(): void
    {
        $this->seed();

        $empresa = $this->empresaAbuso();
        $this->assertNotNull($empresa, 'Esperava a empresa dedicada do padrão de abuso (CNPJ estável).');

        // O padrão dedicado supera 2× o limite (volume_cnpj) — a detecção REAL gera
        // um alerta ABERTO de severidade ALTA ligado à empresa dedicada.
        $alerta = AbuseAlert::query()
            ->where('rule_key', 'volume_cnpj')
            ->where('subject_type', $empresa->getMorphClass())
            ->where('subject_id', $empresa->id)
            ->where('status', AbuseAlertStatus::Aberto)
            ->first();

        $this->assertNotNull($alerta, 'Esperava o alerta de abuso REAL do padrão dedicado (volume_cnpj, aberto).');
        $this->assertSame(AbuseSeverity::Alta, $alerta->severity, 'O volume dedicado deve disparar severidade ALTA.');

        // Acima do limiar → encaminhamento à malha fina pelo caminho de SISTEMA
        // (referred_by_user_id null), NUNCA punição (RN-001).
        $this->assertNotNull($alerta->fine_mesh_referral_id, 'O alerta ALTA deveria encaminhar à malha fina (fine_mesh_referral_id).');
        $this->assertTrue(
            $alerta->fineMeshReferral()->whereNull('referred_by_user_id')->exists(),
            'O encaminhamento deveria ser do sistema (referred_by_user_id null).',
        );

        // Há pelo menos um alerta aberto real no ledger (painel de abuso navegável).
        $this->assertGreaterThanOrEqual(1, AbuseAlert::query()->where('status', AbuseAlertStatus::Aberto)->count());
    }

    public function test_seed_registra_acesso_a_dado_pessoal_pelo_fluxo_real(): void
    {
        $this->seed();

        // O acesso ao detalhe de um processo de terceiro é marcado personal_data
        // pelo AuditService (mesmo call site do ProcessoController@show) — base de
        // medição do painel LGPD (HU-102).
        $this->assertTrue(
            Activity::query()
                ->where('event', 'consulta-processo')
                ->where('personal_data', true)
                ->exists(),
            'Esperava ao menos um acesso a dado pessoal (personal_data=true) pelo fluxo real.',
        );
        $this->assertGreaterThanOrEqual(1, Activity::query()->where('personal_data', true)->count());
    }

    public function test_seed_restaura_o_toggle_de_abuso_desligado(): void
    {
        $this->seed();

        // Degradação honesta: a detecção roda SÓ durante o seed; a feature nasce
        // desligada (a SEDUR liga após calibrar os limiares).
        $this->assertFalse(Settings::enabled('deteccao_abuso'), 'O toggle features.deteccao_abuso deve voltar a OFF após o seed.');
    }

    public function test_seed_leva_processo_dedicado_a_decisao_com_trace_e_um_legado_sem_trace(): void
    {
        $this->seed();

        // Explicabilidade navegável (HU-099): um processo dedicado é DECIDIDO pela
        // análise técnica (a partir da ficha) — a decisão nasce com decision_trace.
        $decidido = ViabilityRequest::query()
            ->where('address_reference', AuditoriaDevSeeder::MARK_DECISAO)
            ->first();
        $this->assertNotNull($decidido, 'Esperava o processo dedicado levado à decisão pela análise técnica.');

        $decisao = $decidido->decision;
        $this->assertNotNull($decisao, 'O processo dedicado deveria ter uma ViabilityDecision real.');
        $this->assertSame('analise_tecnica', $decisao->flow);
        $this->assertNotEmpty($decisao->decision_trace, 'A decisão da análise técnica deve nascer com decision_trace.');

        // Sub-passo "não registrado" (HU-099): uma decisão LEGADA sem trace.
        $legado = ViabilityRequest::query()
            ->where('address_reference', AuditoriaDevSeeder::MARK_LEGADO)
            ->first();
        $this->assertNotNull($legado, 'Esperava o processo dedicado da decisão legada.');
        $this->assertNotNull($legado->decision, 'A decisão legada deveria existir.');
        $this->assertNull($legado->decision->decision_trace, 'A decisão legada NÃO tem decision_trace ("não registrado").');
    }

    public function test_seed_de_auditoria_e_idempotente(): void
    {
        $this->seed();

        $empresa = $this->empresaAbuso();
        $this->assertNotNull($empresa);

        $alertasAntes = AbuseAlert::query()->count();
        $personalAntes = Activity::query()
            ->where('event', 'consulta-processo')
            ->where('personal_data', true)
            ->count();

        $this->seed();

        // Re-seed não duplica: empresa/drafts dedicados, alertas (dedup do alerta
        // aberto), decisões e o acesso a dado pessoal são estáveis.
        $this->assertSame(1, Company::query()->where('cnpj', AuditoriaDevSeeder::CNPJ_ABUSO)->count());
        $this->assertSame(
            AuditoriaDevSeeder::VOLUME_ABUSO,
            ViabilityRequest::query()->where('address_reference', AuditoriaDevSeeder::MARK_ABUSO)->count(),
            'Os drafts do padrão de abuso não podem duplicar no re-seed.',
        );
        $this->assertSame(
            1,
            AbuseAlert::query()
                ->where('rule_key', 'volume_cnpj')
                ->where('subject_type', $empresa->getMorphClass())
                ->where('subject_id', $empresa->id)
                ->count(),
            'O alerta dedicado não pode duplicar no re-seed (idempotência do ledger).',
        );
        $this->assertSame($alertasAntes, AbuseAlert::query()->count(), 'O total de alertas não pode crescer no re-seed.');
        $this->assertSame(
            $personalAntes,
            Activity::query()->where('event', 'consulta-processo')->where('personal_data', true)->count(),
            'O acesso a dado pessoal dedicado não pode duplicar no re-seed.',
        );

        // As decisões dedicadas (análise técnica + legada) também são estáveis.
        $this->assertSame(
            1,
            ViabilityRequest::query()->where('address_reference', AuditoriaDevSeeder::MARK_DECISAO)->count(),
        );
        $this->assertSame(
            1,
            ViabilityRequest::query()->where('address_reference', AuditoriaDevSeeder::MARK_LEGADO)->count(),
        );
        $this->assertSame(2, ViabilityDecision::query()->count());
    }
}
