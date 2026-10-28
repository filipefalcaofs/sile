<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestCpf;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginVincularCpfsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SOCIO_A = '52998224725';

    private const SOCIO_B = '11144477735';

    protected function setUp(): void
    {
        parent::setUp();

        User::factory()->create();
    }

    /**
     * @param  list<string>  $socios
     * @return array<string, mixed>
     */
    private function ruc(array $socios): array
    {
        return ['rowset' => [
            'RUC_GENERAL' => ['RGE_CGC_CPF' => '13927801000149', 'RGE_NOMB' => 'Empresa REGIN Teste'],
            'RUC_ESTAB' => [
                'RES_AREA' => '834',
                'RES_DIRECCION' => 'Rua das Flores',
                'RES_NUME' => '100',
                'RES_URBANIZACION' => 'Centro',
                'RES_ZONA_POSTAL' => '40000000',
            ],
            'GROUPRUC_ACTV_ECON' => ['RUC_ACTV_ECON' => [['RAE_TAE_COD_ACTVD' => '4712100', 'RAE_CALIF_ACTV' => '1']]],
            'GROUPRUC_GEN_PROTOCOLO' => ['RUC_GEN_PROTOCOLO' => [['RGP_TGE_COD_TIP_TAB' => '5', 'RGP_VALOR' => '12345678']]],
            'GROUPRUC_PROF' => ['RUC_PROF' => array_map(fn (string $cpf) => ['RPR_CGC_CPF' => $cpf], $socios)],
        ]];
    }

    /**
     * @return list<string>
     */
    private function socios(ViabilityRequest $processo): array
    {
        return $processo->cpfsVinculados()->where('papel', ViabilityRequestCpf::PAPEL_SOCIO)->orderBy('cpf')->pluck('cpf')->all();
    }

    private function processoRegin(string $protocolo, ?string $solicitanteCpf = null): ViabilityRequest
    {
        return ViabilityRequest::factory()->protocoled()->create([
            'protocol_number' => 'VIA-2026-'.$protocolo,
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => $protocolo,
            'requester_cidadao_id' => null,
            'solicitante_cpf' => $solicitanteCpf,
        ]);
    }

    public function test_protocolar_regin_grava_os_socios(): void
    {
        $recebimento = ReginRecebimento::factory()->create(['protocolo' => '43747', 'corpo' => $this->ruc([self::SOCIO_A, self::SOCIO_B])]);

        $processo = app(ReginProcessoProtocolador::class)->protocolar($recebimento);

        $this->assertNotNull($processo);
        $this->assertSame([self::SOCIO_B, self::SOCIO_A], $this->socios($processo));
        $this->assertNull($processo->requester_cidadao_id);
    }

    public function test_comando_vincula_processos_antigos(): void
    {
        $processo = $this->processoRegin('000101');
        ReginRecebimento::factory()->create(['protocolo' => '000101', 'corpo' => $this->ruc([self::SOCIO_A])]);

        $this->artisan('regin:vincular-cpfs')->assertSuccessful();
        $this->artisan('regin:vincular-cpfs')->assertSuccessful();

        $this->assertSame([self::SOCIO_A], $this->socios($processo));
        $this->assertSame(1, ViabilityRequestCpf::query()->count());
    }

    public function test_comando_dry_run_nao_grava(): void
    {
        $this->processoRegin('000102');
        ReginRecebimento::factory()->create(['protocolo' => '000102', 'corpo' => $this->ruc([self::SOCIO_A])]);

        $this->artisan('regin:vincular-cpfs', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, ViabilityRequestCpf::query()->count());
    }

    public function test_comando_sem_recebimento_vincula_so_o_solicitante(): void
    {
        $processo = $this->processoRegin('000103', self::SOCIO_B);

        $this->artisan('regin:vincular-cpfs')->assertSuccessful();

        $this->assertSame([], $this->socios($processo));
        $this->assertSame([self::SOCIO_B], $processo->cpfsVinculados()->where('papel', ViabilityRequestCpf::PAPEL_SOLICITANTE)->pluck('cpf')->all());
    }
}
