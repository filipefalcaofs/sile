<?php

namespace Tests\Feature\Regin;

use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestCpf;
use App\Services\Regin\ReginSociosExtrator;
use App\Services\Solicitacao\VinculosCpfProcesso;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class VinculosCpfProcessoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SOCIO_A = '52998224725';

    private const SOCIO_B = '11144477735';

    /**
     * @return list<string>
     */
    private function vinculos(ViabilityRequest $processo, string $papel): array
    {
        return $processo->cpfsVinculados()->where('papel', $papel)->orderBy('cpf')->pluck('cpf')->all();
    }

    public function test_extrai_cpfs_de_lista_e_de_objeto_unico(): void
    {
        $lista = ['GROUPRUC_PROF' => ['RUC_PROF' => [
            ['RPR_CGC_CPF' => '529.982.247-25', 'RPR_NOMB' => 'Sócia A'],
            ['RPR_CPF' => '11144477735'],
        ]]];
        $objeto = ['GROUPRUC_PROF' => ['RUC_PROF' => ['RPR_CGC_CPF' => self::SOCIO_A]]];

        $this->assertSame([self::SOCIO_B, self::SOCIO_A], ReginSociosExtrator::cpfs($lista));
        $this->assertSame([self::SOCIO_A], ReginSociosExtrator::cpfs($objeto));
    }

    public function test_ignora_cpf_invalido_cnpj_e_item_com_crc(): void
    {
        $rowset = ['GROUPRUC_PROF' => ['RUC_PROF' => [
            ['RPR_CGC_CPF' => '11111111111'],
            ['RPR_CGC_CPF' => '11222333000181'],
            ['RPR_CGC_CPF' => self::SOCIO_B, 'RPR_NUM_CRC' => 'BA-012345/O'],
            ['RPR_CGC_CPF' => self::SOCIO_A, 'RPR_NUM_CRC' => ''],
        ]]];

        $this->assertSame([self::SOCIO_A], ReginSociosExtrator::cpfs($rowset));
    }

    public function test_sem_ruc_prof_devolve_lista_vazia(): void
    {
        $this->assertSame([], ReginSociosExtrator::cpfs([]));
        $this->assertSame([], ReginSociosExtrator::cpfs(['GROUPRUC_PROF' => '']));
    }

    public function test_sincronizar_grava_socios_e_solicitante(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['solicitante_cpf' => self::SOCIO_B]);

        app(VinculosCpfProcesso::class)->sincronizar($processo, [self::SOCIO_A]);

        $this->assertSame([self::SOCIO_A], $this->vinculos($processo, ViabilityRequestCpf::PAPEL_SOCIO));
        $this->assertSame([self::SOCIO_B], $this->vinculos($processo, ViabilityRequestCpf::PAPEL_SOLICITANTE));
    }

    public function test_sincronizar_remove_ex_socio_e_solicitante_apagado(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['solicitante_cpf' => self::SOCIO_B]);
        $vinculos = app(VinculosCpfProcesso::class);
        $vinculos->sincronizar($processo, [self::SOCIO_A, self::SOCIO_B]);

        $processo->forceFill(['solicitante_cpf' => null])->save();
        $vinculos->sincronizar($processo, [self::SOCIO_B]);

        $this->assertSame([self::SOCIO_B], $this->vinculos($processo, ViabilityRequestCpf::PAPEL_SOCIO));
        $this->assertSame([], $this->vinculos($processo, ViabilityRequestCpf::PAPEL_SOLICITANTE));
    }

    public function test_sincronizar_e_idempotente(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create(['solicitante_cpf' => self::SOCIO_B]);
        $vinculos = app(VinculosCpfProcesso::class);

        $vinculos->sincronizar($processo, [self::SOCIO_A]);
        $vinculos->sincronizar($processo, [self::SOCIO_A]);

        $this->assertSame(2, ViabilityRequestCpf::query()->where('viability_request_id', $processo->id)->count());
    }
}
