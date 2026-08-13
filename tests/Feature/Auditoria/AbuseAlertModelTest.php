<?php

namespace Tests\Feature\Auditoria;

use App\Enums\AbuseAlertStatus;
use App\Enums\AbuseSeverity;
use App\Models\AbuseAlert;
use App\Models\Company;
use App\Models\FineMeshReferral;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AbuseAlertModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_cria_alerta_com_casts_tipados(): void
    {
        $alert = AbuseAlert::factory()->create([
            'evidence' => ['ocorrencias' => 7, 'cnpjs' => ['00000000000191']],
        ]);

        $this->assertInstanceOf(AbuseSeverity::class, $alert->severity);
        $this->assertInstanceOf(AbuseAlertStatus::class, $alert->status);
        $this->assertSame(AbuseAlertStatus::Aberto, $alert->status);
        $this->assertIsArray($alert->evidence);
        $this->assertSame(7, $alert->evidence['ocorrencias']);
        $this->assertInstanceOf(Carbon::class, $alert->detected_at);
    }

    public function test_relacoes_do_alerta(): void
    {
        $referral = FineMeshReferral::factory()->create();
        $request = $referral->viabilityRequest;
        $resolvedBy = User::factory()->create();
        $company = Company::factory()->create();

        $alert = AbuseAlert::factory()->confirmado()->create([
            'viability_request_id' => $request->id,
            'resolved_by_user_id' => $resolvedBy->id,
            'fine_mesh_referral_id' => $referral->id,
            'subject_type' => $company->getMorphClass(),
            'subject_id' => $company->id,
        ]);

        $this->assertTrue($alert->viabilityRequest->is($request));
        $this->assertTrue($alert->resolvedBy->is($resolvedBy));
        $this->assertTrue($alert->fineMeshReferral->is($referral));
        $this->assertTrue($alert->subject->is($company));
    }

    public function test_criacao_de_alerta_e_auditada(): void
    {
        // RN-002: a própria criação do alerta é auditável (HasAuditoria).
        $alert = AbuseAlert::factory()->create();

        $this->assertDatabaseHas('activity_log', [
            'subject_type' => $alert->getMorphClass(),
            'subject_id' => $alert->id,
        ]);
    }

    public function test_indice_unico_parcial_barra_segundo_alerta_aberto(): void
    {
        // Idempotência estrutural: 1 alerta ABERTO por (rule_key, fingerprint).
        AbuseAlert::factory()->create([
            'rule_key' => 'volume_cnpj',
            'fingerprint' => 'fp-123',
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $this->expectException(QueryException::class);

        AbuseAlert::factory()->create([
            'rule_key' => 'volume_cnpj',
            'fingerprint' => 'fp-123',
            'status' => AbuseAlertStatus::Aberto,
        ]);
    }

    public function test_permite_novo_alerta_aberto_apos_anterior_resolvido(): void
    {
        // Confirmado/descartado NÃO bloqueiam um novo alerta aberto futuro com o
        // mesmo (rule_key, fingerprint): o índice parcial só restringe abertos.
        $primeiro = AbuseAlert::factory()->create([
            'rule_key' => 'volume_cnpj',
            'fingerprint' => 'fp-789',
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $primeiro->update(['status' => AbuseAlertStatus::Confirmado]);

        $segundo = AbuseAlert::factory()->create([
            'rule_key' => 'volume_cnpj',
            'fingerprint' => 'fp-789',
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $segundo->update(['status' => AbuseAlertStatus::Descartado]);

        AbuseAlert::factory()->create([
            'rule_key' => 'volume_cnpj',
            'fingerprint' => 'fp-789',
            'status' => AbuseAlertStatus::Aberto,
        ]);

        $this->assertSame(
            3,
            AbuseAlert::query()
                ->where('rule_key', 'volume_cnpj')
                ->where('fingerprint', 'fp-789')
                ->count(),
        );
    }
}
