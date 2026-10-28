<?php

namespace Tests;

use App\Jobs\DecidirFluxoExpressoJob;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    /**
     * Desligue (false) em testes que exercitam o worker REAL da fila
     * (queue:work/failed_jobs) — o fake parcial impediria o pop do worker.
     */
    protected bool $fakeExpressoDecisionJob = true;

    protected function setUp(): void
    {
        // Isolamento sqlite × pgsql_testing: o RefreshDatabase guarda o "já
        // migrou" num estático de processo, sem registrar a conexão. Se um teste
        // do grupo postgis migrou a pgsql_testing, o teste sqlite seguinte
        // pularia a própria migração (:memory:) e quebraria com "no such table".
        // O reset acontece só na borda postgis → sqlite; entre testes postgis a
        // base migrada é reutilizada normalmente.
        if (! $this instanceof PostgisTestCase && PostgisTestCase::$migrouPgsql) {
            RefreshDatabaseState::$migrated = false;
            RefreshDatabaseState::$lazilyRefreshed = false;
            PostgisTestCase::$migrouPgsql = false;
        }

        $framework = dirname(__DIR__).'/storage/framework';

        foreach (['cache/data', 'sessions', 'views', 'testing'] as $diretorio) {
            $caminho = $framework.'/'.$diretorio;

            if (! is_dir($caminho)) {
                mkdir($caminho, 0775, true);
            }
        }

        parent::setUp();

        $this->withoutVite();

        // GeoServer SEDUR é integração HTTP real. A suíte desliga o toggle por
        // padrão — testes do cliente WFS ligam e usam Http::fake.
        config(['viabiliza.features.geoserver_zona' => false]);
        config(['viabiliza.features.geoserver_enderecamento' => false]);
        config(['viabiliza.features.geoserver_via_classe' => false]);

        // ArcGIS municipal (SEMAP) é integração HTTP real: desligada por
        // padrão na suíte; o teste do cliente liga e usa Http::fake.
        config(['viabiliza.features.arcgis_semap' => false]);

        // A API de estatísticas da REDESIM é integração HTTP real: desligada
        // por padrão na suíte; os testes do cliente ligam e usam Http::fake.
        config(['viabiliza.features.benchmark_redesim' => false]);

        // Serviços do portal nascem desligados no catálogo. A suíte liga por
        // padrão; o teste do interruptor desliga. O forget evita cache de um
        // teste anterior no driver array.
        config(['viabiliza.features.portal_servicos' => true]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');

        // A migration cria a linha do interruptor com padrão desligado; sem ela
        // a suíte cai no config acima. Testes que semeiam o catálogo recriam a linha.
        if (Schema::hasTable('parameters')) {
            DB::table('parameters')->where('key', 'features.portal_servicos')->delete();
        }

        // O gatilho do fluxo expresso (listener auto-descoberto AvaliarFluxoExpresso)
        // despacha o DecidirFluxoExpressoJob a CADA protocolo (SolicitacaoProtocolada,
        // EP08). Na suíte a fila é sync (phpunit.xml): o job rodaria INLINE e a
        // decisão — que em produção é ASSÍNCRONA, fora do request do protocolo —
        // mudaria o status logo após protocolar, acoplando testes de protocolo à
        // decisão (EP09). Fakeamos SÓ esse job por padrão; os demais jobs seguem
        // reais/sync. Quem exercita a decisão chama o serviço/job direto, dispara
        // o evento com Bus::fake (testes do gatilho) ou processa a fila.
        if ($this->fakeExpressoDecisionJob) {
            Queue::fake([DecidirFluxoExpressoJob::class]);
        }
    }
}
