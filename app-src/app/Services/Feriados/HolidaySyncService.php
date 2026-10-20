<?php

namespace App\Services\Feriados;

use App\Models\Holiday;

/**
 * Grava no cadastro os feriados do ano vindos da FeriadosAPI. Data já
 * cadastrada à mão (ou pelo seeder) nunca é tocada; registro que veio da API
 * só tem o nome atualizado — a situação (ativo/inativo) é decisão da SEDUR.
 */
class HolidaySyncService
{
    public function __construct(private FeriadosApiClient $client) {}

    /**
     * @return array{criados: int, atualizados: int, ignorados: int}
     */
    public function sync(int $year): array
    {
        $report = ['criados' => 0, 'atualizados' => 0, 'ignorados' => 0];

        foreach ($this->client->holidaysFor($year) as $item) {
            $holiday = Holiday::query()->whereDate('date', $item['date']->toDateString())->first();

            if ($holiday === null) {
                Holiday::create([
                    'date' => $item['date'],
                    'name' => $item['name'],
                    'recurring_annually' => false,
                    'active' => true,
                    'source' => 'api',
                    'type' => $item['type'],
                ]);
                $report['criados']++;

                continue;
            }

            if ($holiday->source === 'api' && $holiday->name !== $item['name']) {
                $holiday->update(['name' => $item['name'], 'type' => $item['type']]);
                $report['atualizados']++;

                continue;
            }

            $report['ignorados']++;
        }

        return $report;
    }
}
