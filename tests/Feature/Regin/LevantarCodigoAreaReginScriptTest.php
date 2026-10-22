<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\IntegrationCall;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class LevantarCodigoAreaReginScriptTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  list<string>  $argumentos
     */
    private function rodarScript(array $argumentos = []): string
    {
        $app = app();

        ob_start();
        include base_path('scripts/levantar-codigo-area-regin.php');

        return (string) ob_get_clean();
    }

    private function envio(string $protocolo, ?int $area, string $outcome = 'sucesso', int $codFuncao = 110): void
    {
        $analise = ['STATUS_ANALISE' => 2];

        if ($area !== null) {
            $analise = ['CODIGO_AREA' => $area] + $analise;
        }

        IntegrationCall::query()->create([
            'direction' => 'saida',
            'integration' => 'regin',
            'method' => 'POST',
            'url' => 'http://10.57.247.9:8080/api_integracao/recebe',
            'http_status' => $outcome === 'sucesso' ? 200 : 500,
            'outcome' => $outcome,
            'request_body' => json_encode([
                'protocolo' => $protocolo,
                'codFuncao' => $codFuncao,
                'dadosProcesso' => ['ANALISES' => ['AREA' => [$analise]]],
            ]),
        ]);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function secoes(string $saida): array
    {
        preg_match('/== A REENVIAR.*?==\n(.*?)\n== SUCESSO/s', $saida, $reenviar);
        preg_match('/== SUCESSO NA AUDITORIA.*?==\n(.*?)\n== JÁ ENVIADOS/s', $saida, $semCorpo);
        preg_match('/== JÁ ENVIADOS.*?==\n(.*?)\nA reenviar/s', $saida, $corretos);

        return [$reenviar[1] ?? '', $semCorpo[1] ?? '', $corretos[1] ?? ''];
    }

    public function test_separa_os_aceitos_com_area_1_dos_que_ja_foram_com_70(): void
    {
        $this->envio('BAB2601852710', 1);
        $this->envio('BAP2601000002', 70);

        [$reenviar, , $corretos] = $this->secoes($this->rodarScript());

        $this->assertStringContainsString('BAB2601852710  CODIGO_AREA=1', $reenviar);
        $this->assertStringNotContainsString('BAP2601000002', $reenviar);
        $this->assertStringContainsString('BAP2601000002', $corretos);
    }

    public function test_reenvio_posterior_com_70_tira_o_protocolo_da_lista(): void
    {
        $this->envio('BAB2601852710', 1);
        $this->envio('BAB2601852710', 70);

        [$reenviar, , $corretos] = $this->secoes($this->rodarScript());

        $this->assertSame('', trim($reenviar));
        $this->assertStringContainsString('BAB2601852710', $corretos);
    }

    public function test_envio_sem_a_tag_entra_para_reenvio(): void
    {
        $this->envio('BAP2601000003', null);

        [$reenviar] = $this->secoes($this->rodarScript());

        $this->assertStringContainsString('BAP2601000003  CODIGO_AREA=ausente', $reenviar);
    }

    public function test_ignora_falhas_e_outras_funcoes(): void
    {
        $this->envio('BAP2601000004', 1, outcome: 'falha');
        $this->envio('BAP2601000005', 1, codFuncao: 100);

        $saida = $this->rodarScript();

        $this->assertStringNotContainsString('BAP2601000004', $saida);
        $this->assertStringNotContainsString('BAP2601000005', $saida);
        $this->assertStringContainsString('A reenviar: 0', $saida);
    }

    public function test_sucesso_na_auditoria_sem_corpo_gravado_aparece_como_area_desconhecida(): void
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => 'BAP2601000006',
        ]);

        Activity::query()->forceCreate([
            'log_name' => 'integracoes',
            'event' => 'regin-parecer',
            'description' => 'parecer enviado',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
            'result' => 'sucesso',
        ]);

        [$reenviar, $semCorpo] = $this->secoes($this->rodarScript());

        $this->assertStringContainsString('BAP2601000006', $semCorpo);
        $this->assertStringNotContainsString('BAP2601000006', $reenviar);
    }

    public function test_filtra_por_protocolo(): void
    {
        $this->envio('BAB2601852710', 1);
        $this->envio('BAP2601000007', 1);

        [$reenviar] = $this->secoes($this->rodarScript(['bab2601852710']));

        $this->assertStringContainsString('BAB2601852710', $reenviar);
        $this->assertStringNotContainsString('BAP2601000007', $reenviar);
    }
}
