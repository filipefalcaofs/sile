<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dado de PARTIDA dos feriados (HU-137), substituível pela lista oficial da
 * SEDUR. Semeia APENAS os feriados NACIONAIS de data fixa (inquestionáveis,
 * dado público) marcados como recorrentes anuais. NUNCA semeia feriado
 * MUNICIPAL de Salvador (2 de Julho, padroeiras etc.) nem feriados móveis
 * (Carnaval, Sexta-feira Santa, Corpus Christi): a lista municipal/móvel oficial
 * é pendência SEDUR — degradação honesta, sem feriado inventado.
 *
 * Enquanto não há feriado MUNICIPAL específico cadastrado, o provider reporta
 * hasOfficialCalendar=false e o cálculo de dias úteis desconta só fins de semana
 * + estes feriados nacionais fixos, com a ressalva visível no relatório.
 *
 * Idempotente: updateOrCreate por data (âncora de ano fixa; o provider compara
 * mês/dia para os recorrentes). Rodar N vezes mantém a contagem.
 */
class HolidaySeeder extends Seeder
{
    /**
     * Ano-âncora de armazenamento dos feriados recorrentes. O ano é irrelevante
     * para a lógica (o provider compara mês/dia); serve só para uma data válida
     * e estável no banco, garantindo a idempotência do updateOrCreate.
     */
    private const ANCHOR_YEAR = 2026;

    public function run(): void
    {
        foreach ($this->nationalFixedHolidays() as [$month, $day, $name]) {
            // Carbon (não string) na chave de busca: o binding casa com o valor
            // armazenado pelo cast date (Y-m-d H:i:s), garantindo a idempotência
            // do updateOrCreate (string crua não casaria e duplicaria).
            Holiday::query()->updateOrCreate(
                ['date' => Carbon::create(self::ANCHOR_YEAR, $month, $day)],
                [
                    'name' => $name,
                    'recurring_annually' => true,
                    'active' => true,
                ],
            );
        }
    }

    /**
     * Feriados nacionais de data fixa (Lei 662/1949 e Lei 6.802/1980).
     *
     * @return list<array{0: int, 1: int, 2: string}>
     */
    private function nationalFixedHolidays(): array
    {
        return [
            [1, 1, 'Confraternização Universal'],
            [4, 21, 'Tiradentes'],
            [5, 1, 'Dia do Trabalho'],
            [9, 7, 'Independência do Brasil'],
            [10, 12, 'Nossa Senhora Aparecida'],
            [11, 2, 'Finados'],
            [11, 15, 'Proclamação da República'],
            [12, 25, 'Natal'],
        ];
    }
}
