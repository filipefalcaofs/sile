<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\Activity;
use App\Models\IntegrationCall;
use App\Models\ViabilityDecision;
use App\Models\ViabilityRequest;
use App\Services\Regin\ReginParecerNotifier;
use App\Services\Regin\ReginUnavailableException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginReenviarPareceresCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function processoDecidido(string $protocolo): ViabilityRequest
    {
        $request = ViabilityRequest::factory()->protocoled()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => $protocolo,
            'protocol_number' => 'VIA-2026-'.substr($protocolo, -6),
        ]);
        ViabilityDecision::factory()->create([
            'viability_request_id' => $request->id,
            'tvl_product_number' => 'TVL-'.substr($protocolo, -6),
        ]);

        return $request;
    }

    private function notifier(bool $confirma): object
    {
        $notifier = new class($confirma) implements ReginParecerNotifier
        {
            /** @var list<string> */
            public array $enviados = [];

            public function __construct(private bool $confirma) {}

            public function notifyParecer(ViabilityRequest $request, ViabilityDecision $decision): void
            {
                $this->enviados[] = (string) $request->external_reference;

                if (! $this->confirma) {
                    throw new ReginUnavailableException(motivo: 'REGIN não confirmou o recebimento do parecer.');
                }
            }
        };

        $this->app->instance(ReginParecerNotifier::class, $notifier);

        return $notifier;
    }

    public function test_dry_run_lista_sem_enviar(): void
    {
        $this->processoDecidido('BAP2601000001');
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --dry-run')
            ->expectsOutputToContain('BAP2601000001: seria reenviado')
            ->assertSuccessful();

        $this->assertSame([], $notifier->enviados);
    }

    public function test_reenvia_o_pendente_e_audita_sucesso(): void
    {
        $processo = $this->processoDecidido('BAP2601000001');
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres')->assertSuccessful();

        $this->assertSame(['BAP2601000001'], $notifier->enviados);
        $this->assertSame('sucesso', Activity::query()->where('event', 'regin-parecer')->where('subject_id', $processo->id)->value('result'));
    }

    public function test_quem_ja_foi_confirmado_nao_e_reenviado(): void
    {
        $this->processoDecidido('BAP2601000001');
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres')->assertSuccessful();
        $this->artisan('regin:reenviar-pareceres')->assertSuccessful();

        $this->assertSame(['BAP2601000001'], $notifier->enviados);
    }

    public function test_para_apos_falhas_seguidas_e_nao_confirma(): void
    {
        foreach (['BAP2601000001', 'BAP2601000002', 'BAP2601000003', 'BAP2601000004'] as $protocolo) {
            $this->processoDecidido($protocolo);
        }
        $notifier = $this->notifier(false);

        $this->artisan('regin:reenviar-pareceres --parar-apos=2')
            ->expectsOutputToContain('Interrompido após 2 falhas seguidas')
            ->assertFailed();

        $this->assertCount(2, $notifier->enviados);
    }

    public function test_exceto_nao_reenvia_os_protocolos_informados(): void
    {
        $this->processoDecidido('BAP2601000001');
        $this->processoDecidido('BAP2601000002');
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --exceto=BAP2601000001')
            ->expectsOutputToContain('BAP2601000002: enviado e confirmado pela JUCEB')
            ->assertSuccessful();

        $this->assertSame(['BAP2601000002'], $notifier->enviados);
    }

    public function test_processo_sem_decisao_nao_e_reenviado(): void
    {
        ViabilityRequest::factory()->protocoled()->create([
            'origin' => ViabilityRequestOrigin::Regin,
            'external_reference' => 'BAP2601000009',
        ]);
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres')->assertSuccessful();

        $this->assertSame([], $notifier->enviados);
    }

    private function confirmar(ViabilityRequest $processo, ?int $area): void
    {
        Activity::query()->create([
            'log_name' => 'integracoes',
            'event' => 'regin-parecer',
            'description' => 'ok',
            'subject_type' => $processo->getMorphClass(),
            'subject_id' => $processo->id,
            'result' => 'sucesso',
        ]);

        if ($area !== null) {
            IntegrationCall::query()->create([
                'direction' => 'saida',
                'integration' => 'regin',
                'method' => 'POST',
                'url' => 'https://regin.example/recebe',
                'outcome' => 'sucesso',
                'request_body' => json_encode([
                    'codFuncao' => 110,
                    'protocolo' => $processo->external_reference,
                    'dadosProcesso' => ['ANALISES' => ['AREA' => [['CODIGO_AREA' => $area]]]],
                ]),
            ]);
        }
    }

    public function test_forcar_reenvia_o_protocolo_ja_confirmado(): void
    {
        $this->confirmar($this->processoDecidido('BAP2601000001'), 1);
        $this->processoDecidido('BAP2601000002');
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --forcar --protocolo=BAP2601000001')->assertSuccessful();

        $this->assertSame(['BAP2601000001'], $notifier->enviados);
    }

    public function test_forcar_sem_protocolo_e_recusado(): void
    {
        $this->confirmar($this->processoDecidido('BAP2601000001'), 1);
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --forcar')->assertFailed();

        $this->assertSame([], $notifier->enviados);
    }

    public function test_area_errada_reenvia_so_quem_foi_aceito_com_area_diferente(): void
    {
        $this->confirmar($this->processoDecidido('BAP2601000001'), 1);
        $this->confirmar($this->processoDecidido('BAP2601000002'), 70);
        $this->confirmar($this->processoDecidido('BAP2601000003'), 1);
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --area-errada --protocolo=BAP2601000001 --protocolo=BAP2601000002')->assertSuccessful();
        $this->assertSame(['BAP2601000001'], $notifier->enviados);

        $this->artisan('regin:reenviar-pareceres --area-errada --dry-run')
            ->expectsOutputToContain('BAP2601000003: seria reenviado')
            ->assertSuccessful();
    }

    public function test_area_errada_considera_o_envio_aceito_mais_recente(): void
    {
        $processo = $this->processoDecidido('BAP2601000001');
        $this->confirmar($processo, 1);
        $this->confirmar($processo, 70);
        $notifier = $this->notifier(true);

        $this->artisan('regin:reenviar-pareceres --area-errada')->assertSuccessful();

        $this->assertSame([], $notifier->enviados);
    }
}
