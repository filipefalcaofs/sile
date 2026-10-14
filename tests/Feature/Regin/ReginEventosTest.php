<?php

namespace Tests\Feature\Regin;

use App\Models\ReginRecebimento;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use App\Services\Regin\ReginEventos;
use App\Services\Regin\ReginProcessoProtocolador;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Evento do protocolo enviado pela JUCEB (PEV_COD_EVENTO / PEV_NOME_EVENTO):
 * é o "serviço" legível do processo, e um BAP pode trazer vários.
 */
class ReginEventosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const INSCRICAO = ['PEV_COD_EVENTO' => '101', 'PEV_NOME_EVENTO' => 'Inscrição de primeiro estabelecimento'];

    private const ALTERACAO_ATIVIDADES = ['PEV_COD_EVENTO' => '244', 'PEV_NOME_EVENTO' => 'Alteração de atividades econômicas (principal e secundárias)'];

    /**
     * @param  array<string, mixed>  $corpo
     * @param  array<string, mixed>  $envelope
     */
    private function recebimento(array $corpo, array $envelope = [], string $protocolo = 'BAP2601848001'): ReginRecebimento
    {
        return ReginRecebimento::factory()->create([
            'protocolo' => $protocolo,
            'corpo' => $corpo,
            'envelope' => $envelope + ['protocolo' => $protocolo],
        ]);
    }

    /**
     * @param  list<array<string, string>>|array<string, string>  $eventos
     * @return array<string, mixed>
     */
    private function corpoComEventos(array $eventos): array
    {
        return ['rowset' => ['GROUPPSC_PROT_EVENTO_RFB' => ['PSC_PROT_EVENTO_RFB' => $eventos]]];
    }

    public function test_extrai_os_eventos_do_rowset_em_lista(): void
    {
        $eventos = ReginEventos::doRecebimento(
            $this->recebimento($this->corpoComEventos([self::INSCRICAO, self::ALTERACAO_ATIVIDADES])),
        );

        $this->assertSame([
            ['codigo' => '101', 'nome' => 'Inscrição de primeiro estabelecimento'],
            ['codigo' => '244', 'nome' => 'Alteração de atividades econômicas (principal e secundárias)'],
        ], $eventos);
    }

    public function test_evento_unico_chega_como_objeto_e_e_normalizado(): void
    {
        $eventos = ReginEventos::doRecebimento($this->recebimento($this->corpoComEventos(self::INSCRICAO)));

        $this->assertSame([['codigo' => '101', 'nome' => 'Inscrição de primeiro estabelecimento']], $eventos);
    }

    public function test_le_o_rowset_do_envelope_quando_o_corpo_nao_o_traz(): void
    {
        $eventos = ReginEventos::doRecebimento($this->recebimento(
            ['INFORMACOES_COMPLEMENTARES' => ['RES_AREA' => 834]],
            ['json' => $this->corpoComEventos(self::INSCRICAO)],
        ));

        $this->assertSame('101', $eventos[0]['codigo']);
    }

    public function test_cai_para_o_xml_e_corrige_o_acento_corrompido(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><Ruc><ROWSET><GROUPPSC_PROT_EVENTO_RFB>'
            .'<PSC_PROT_EVENTO_RFB><PEV_COD_EVENTO>211</PEV_COD_EVENTO>'
            .'<PEV_NOME_EVENTO>AlteraÃ§Ã£o de endereÃ§o dentro do mesmo municÃ­pio</PEV_NOME_EVENTO></PSC_PROT_EVENTO_RFB>'
            .'</GROUPPSC_PROT_EVENTO_RFB></ROWSET></Ruc>';

        $eventos = ReginEventos::doRecebimento($this->recebimento(['rowset' => []], ['xml' => $xml]));

        $this->assertSame([['codigo' => '211', 'nome' => 'Alteração de endereço dentro do mesmo município']], $eventos);
    }

    public function test_ignora_evento_repetido_e_linha_sem_codigo_ou_nome(): void
    {
        $eventos = ReginEventos::doRecebimento($this->recebimento($this->corpoComEventos([
            self::INSCRICAO,
            self::INSCRICAO,
            ['PEV_COD_EVENTO' => '', 'PEV_NOME_EVENTO' => 'Sem código'],
            ['PEV_COD_EVENTO' => '999', 'PEV_NOME_EVENTO' => ' '],
        ])));

        $this->assertCount(1, $eventos);
    }

    public function test_sem_eventos_no_envelope_devolve_lista_vazia(): void
    {
        $this->assertSame([], ReginEventos::doRecebimento($this->recebimento(['rowset' => []])));
    }

    public function test_protocolar_grava_os_eventos_no_processo(): void
    {
        User::factory()->create();
        $recebimento = $this->recebimento(array_replace_recursive($this->corpoMinimo(), $this->corpoComEventos([
            self::INSCRICAO,
            self::ALTERACAO_ATIVIDADES,
        ])));

        $processo = app(ReginProcessoProtocolador::class)->protocolar($recebimento);

        $this->assertNotNull($processo);
        $this->assertSame(
            ['101', '244'],
            $processo->eventos()->pluck('codigo')->all(),
        );
    }

    public function test_comando_preenche_os_eventos_dos_processos_ja_recebidos(): void
    {
        $semEventos = ViabilityRequest::factory()->create(['external_reference' => 'BAP2601848001']);
        $comEventos = ViabilityRequest::factory()->create(['external_reference' => 'BAP2601848002']);
        ViabilityRequestEvento::factory()->create(['viability_request_id' => $comEventos->id, 'codigo' => '101', 'nome' => 'Existente']);
        $this->recebimento($this->corpoComEventos([self::INSCRICAO]), [], 'BAP2601848001');
        $this->recebimento($this->corpoComEventos([self::INSCRICAO]), [], 'BAP2601848002');

        $this->artisan('regin:preencher-eventos')->assertSuccessful();
        $this->artisan('regin:preencher-eventos')->assertSuccessful();

        $this->assertSame(['101'], $semEventos->eventos()->pluck('codigo')->all());
        $this->assertSame(['Existente'], $comEventos->eventos()->pluck('nome')->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function corpoMinimo(): array
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
        ]];
    }
}
