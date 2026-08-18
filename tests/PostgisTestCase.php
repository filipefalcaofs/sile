<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Throwable;

/**
 * Base dos testes espaciais (grupo postgis). Aponta a conexão default para
 * pgsql_testing (Postgres + PostGIS) e migra ali via RefreshDatabase —
 * sile_testing, NUNCA o banco de dev.
 *
 * GUARD DE HONESTIDADE (sem fachada de teste): markTestSkipped SÓ quando NÃO
 * há servidor Postgres alcançável (máquina sem o container). Com o servidor
 * de pé, qualquer problema (banco/extensão ausente) vira FALHA, nunca skip —
 * um teste do grupo postgis jamais "passa" sem rodar SQL espacial de verdade.
 * O CI define POSTGIS_TESTS_REQUIRED=true para transformar qualquer skip em
 * falha também na ausência do servidor.
 */
#[Group('postgis')]
abstract class PostgisTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @return Application
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        // Aponta a conexão default para o Postgres + PostGIS ANTES do migrate
        // (RefreshDatabase resolve a default em setUpTraits).
        $app['config']->set('database.default', 'pgsql_testing');

        return $app;
    }

    /**
     * Hook do RefreshDatabase: roda ANTES de migrar, a cada teste.
     */
    protected function beforeRefreshingDatabase(): void
    {
        $conn = config('database.connections.pgsql_testing');
        $required = filter_var(env('POSTGIS_TESTS_REQUIRED', false), FILTER_VALIDATE_BOOL);

        // 1) Servidor Postgres alcançável? Distingue "máquina sem Postgres"
        //    (skip legítimo) de "servidor de pé mas mal configurado" (falha).
        $socket = @fsockopen($conn['host'], (int) $conn['port'], $errno, $errstr, 2);

        if ($socket === false) {
            $msg = "Servidor Postgres de teste inacessível em {$conn['host']}:{$conn['port']} ({$errstr}).";

            if ($required) {
                $this->fail("POSTGIS_TESTS_REQUIRED=true mas {$msg} O grupo postgis NÃO pode ser pulado neste ambiente.");
            }

            $this->markTestSkipped("{$msg} Suba o container de dev (porta 5433) e rode 'php artisan geo:preparar-banco-de-testes'.");
        }

        fclose($socket);

        // 2) Servidor de pé => execução OBRIGATÓRIA. Banco/extensão ausentes
        //    são FALHA (com a orientação do preflight), nunca skip silencioso.
        try {
            DB::connection('pgsql_testing')->statement('CREATE EXTENSION IF NOT EXISTS postgis');
        } catch (Throwable $e) {
            $this->fail(
                'Servidor Postgres de pé mas o banco de teste espacial não está pronto. '
                ."Rode 'php artisan geo:preparar-banco-de-testes'. Erro: ".$e->getMessage()
            );
        }
    }
}
