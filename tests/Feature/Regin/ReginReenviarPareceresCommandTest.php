<?php

namespace Tests\Feature\Regin;

use App\Enums\ViabilityRequestOrigin;
use App\Models\Activity;
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
}
