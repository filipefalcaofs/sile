<?php

namespace Tests\Feature\Motor;

use App\Enums\EtapaMotor;
use App\Enums\SituacaoEtapa;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEtapa;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ViabilityRequestEtapaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_etapa_pertence_ao_processo_e_converte_enums(): void
    {
        $etapa = ViabilityRequestEtapa::factory()->create([
            'etapa' => EtapaMotor::Zona,
            'situacao' => SituacaoEtapa::Indisponivel,
        ]);

        $processo = ViabilityRequest::query()->findOrFail($etapa->viability_request_id);

        $this->assertSame(EtapaMotor::Zona, $processo->etapas()->first()->etapa);
        $this->assertSame(SituacaoEtapa::Indisponivel, $processo->etapas()->first()->situacao);
    }

    public function test_processo_nao_repete_a_mesma_etapa(): void
    {
        $processo = ViabilityRequest::factory()->create();
        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Via]);

        $this->expectException(UniqueConstraintViolationException::class);

        ViabilityRequestEtapa::factory()->create(['viability_request_id' => $processo->id, 'etapa' => EtapaMotor::Via]);
    }

    public function test_cada_etapa_declara_o_servico_de_terceiro_e_a_ordem(): void
    {
        $this->assertSame('inscricao_imobiliaria', EtapaMotor::LocalizacaoInscricao->servico());
        $this->assertSame('geoserver_zona', EtapaMotor::Zona->servico());
        $this->assertLessThan(EtapaMotor::Zona->ordem(), EtapaMotor::LocalizacaoInscricao->ordem());
        $this->assertLessThan(EtapaMotor::RegraTerritorial->ordem(), EtapaMotor::Bairro->ordem());
    }

    public function test_config_traz_os_intervalos_do_reprocessamento(): void
    {
        $this->assertSame(15, config('sile.reprocessamento.intervalo_inicial_min'));
        $this->assertSame(4, config('sile.reprocessamento.tentativas_iniciais'));
        $this->assertSame(60, config('sile.reprocessamento.intervalo_posterior_min'));
        $this->assertSame(24, config('sile.reprocessamento.janela_horas'));
    }
}
