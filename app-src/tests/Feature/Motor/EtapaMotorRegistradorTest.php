<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Services\Motor\EtapaMotorRegistrador;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EtapaMotorRegistradorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(now()->startOfMinute());
    }

    public function test_falha_agenda_a_proxima_tentativa_em_quinze_minutos(): void
    {
        $processo = ViabilityRequest::factory()->create();

        $etapa = app(EtapaMotorRegistrador::class)
            ->registrar($processo, EtapaMotor::LocalizacaoInscricao, SituacaoEtapa::Indisponivel, 'sem resposta');

        $this->assertSame(SituacaoEtapa::Indisponivel, $etapa->situacao);
        $this->assertSame('inscricao_imobiliaria', $etapa->servico);
        $this->assertSame(1, $etapa->tentativas);
        $this->assertTrue($etapa->primeira_falha_em->equalTo(now()));
        $this->assertTrue($etapa->proxima_tentativa_em->equalTo(now()->addMinutes(15)));
        $this->assertNull($etapa->esgotada_em);
    }

    public function test_depois_das_tentativas_iniciais_o_intervalo_passa_a_uma_hora(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);

        for ($i = 0; $i < 5; $i++) {
            $etapa = $registrador->registrar($processo, EtapaMotor::Zona, SituacaoEtapa::Indisponivel);
        }

        $this->assertSame(5, $etapa->tentativas);
        $this->assertTrue($etapa->proxima_tentativa_em->equalTo(now()->addMinutes(60)));
    }

    public function test_esgota_quando_a_proxima_tentativa_passaria_da_janela_de_vinte_e_quatro_horas(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);
        $registrador->registrar($processo, EtapaMotor::Via, SituacaoEtapa::Indisponivel);

        $this->travel(23 * 60 + 50)->minutes();
        $etapa = $registrador->registrar($processo, EtapaMotor::Via, SituacaoEtapa::Indisponivel);

        $this->assertNotNull($etapa->esgotada_em);
        $this->assertNull($etapa->proxima_tentativa_em);
    }

    public function test_sucesso_limpa_a_agenda_e_a_falha(): void
    {
        $processo = ViabilityRequest::factory()->create();
        $registrador = app(EtapaMotorRegistrador::class);
        $registrador->registrar($processo, EtapaMotor::Bairro, SituacaoEtapa::Indisponivel, 'fora do ar');

        $etapa = $registrador->registrar($processo, EtapaMotor::Bairro, SituacaoEtapa::Ok);

        $this->assertSame(SituacaoEtapa::Ok, $etapa->situacao);
        $this->assertSame(2, $etapa->tentativas);
        $this->assertNull($etapa->proxima_tentativa_em);
        $this->assertNull($etapa->primeira_falha_em);
        $this->assertNull($etapa->motivo);
        $this->assertSame(1, $processo->etapas()->count());
    }

    public function test_toda_tentativa_deixa_auditoria_sem_dado_pessoal(): void
    {
        $processo = ViabilityRequest::factory()->create();

        app(EtapaMotorRegistrador::class)
            ->registrar($processo, EtapaMotor::Zona, SituacaoEtapa::Indisponivel, 'GeoServer sem resposta');

        $log = Activity::query()->where('log_name', 'motor')->where('event', 'etapa')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('zona', $log->properties['etapa']);
        $this->assertSame('geoserver_zona', $log->properties['servico']);
        $this->assertSame('indisponivel', $log->properties['situacao']);
        $this->assertSame($processo->id, $log->properties['viability_request_id']);
    }
}
