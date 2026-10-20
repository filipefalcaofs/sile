<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Dia de negócio dos indicadores. O banco grava em UTC (config app.timezone) e
 * a SEDUR trabalha no horário de Salvador — o mesmo fuso em que a tela exibe
 * (resources/js/lib/datas.ts). Uma data informada num filtro é um dia de
 * Salvador; aqui ela vira o intervalo UTC que o cobre inteiro.
 */
final class FusoNegocio
{
    public const NOME = 'America/Bahia';

    /** Primeiro instante do dia local, em UTC, para comparar com as colunas gravadas. */
    public static function inicioDoDia(string $data): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $data, self::NOME)->startOfDay()->utc();
    }

    /** Último instante do dia local, em UTC (limite inclusivo). */
    public static function fimDoDia(string $data): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $data, self::NOME)->endOfDay()->utc();
    }

    /** Agora, no fuso de Salvador — base de "hoje" e das janelas móveis. */
    public static function hoje(): Carbon
    {
        return Carbon::now(self::NOME);
    }

    /** O instante convertido para o horário de Salvador (exibição e exportação). */
    public static function local(?DateTimeInterface $instante): ?Carbon
    {
        return $instante === null ? null : Carbon::instance($instante)->setTimezone(self::NOME);
    }

    /**
     * Expressão SQL do dia local de uma coluna gravada em UTC, para agrupar
     * séries por dia. No SQLite (testes) não há base de fusos: usa o
     * deslocamento atual de Salvador (−3 h, sem horário de verão).
     */
    public static function diaSql(string $coluna, ?string $driver = null): string
    {
        $driver ??= DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            return "date(({$coluna} AT TIME ZONE 'UTC') AT TIME ZONE '".self::NOME."')";
        }

        $minutos = Carbon::now(self::NOME)->utcOffset();

        return "date({$coluna}, '".($minutos >= 0 ? '+' : '').$minutos." minutes')";
    }
}
