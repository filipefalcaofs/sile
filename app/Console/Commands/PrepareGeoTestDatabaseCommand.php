<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Preflight idempotente dos testes espaciais: cria o banco sile_testing e
 * garante a extensão postgis no Postgres de dev (porta 5433). Sem este banco
 * o grupo @group postgis não teria onde rodar (o container só cria `sile`).
 * NÃO toca o banco de dev `sile`.
 */
class PrepareGeoTestDatabaseCommand extends Command
{
    protected $signature = 'geo:preparar-banco-de-testes';

    protected $description = 'Cria o banco de testes espaciais (sile_testing) + extensão postgis — idempotente';

    public function handle(): int
    {
        $testDb = (string) config('database.connections.pgsql_testing.database');

        // Defesa: o nome é interpolado no DDL (CREATE DATABASE não aceita
        // prepared statement). Rejeita qualquer coisa fora de [A-Za-z0-9_].
        if (! preg_match('/^[A-Za-z0-9_]+$/', $testDb)) {
            $this->error("Nome de banco de teste inválido: '{$testDb}'. Ajuste DB_TEST_DATABASE.");

            return self::FAILURE;
        }

        // CREATE DATABASE não roda em transação nem como prepared statement →
        // unprepared. Conecta no 'pgsql' (banco sile) só para criar o outro.
        $exists = DB::connection('pgsql')->selectOne('SELECT 1 FROM pg_database WHERE datname = ?', [$testDb]);

        if ($exists === null) {
            DB::connection('pgsql')->unprepared('CREATE DATABASE "'.$testDb.'"');
            $this->info("Banco {$testDb} criado.");
        } else {
            $this->line("Banco {$testDb} já existe.");
        }

        DB::connection('pgsql_testing')->statement('CREATE EXTENSION IF NOT EXISTS postgis');
        $this->info("Extensão postgis garantida em {$testDb}.");

        return self::SUCCESS;
    }
}
