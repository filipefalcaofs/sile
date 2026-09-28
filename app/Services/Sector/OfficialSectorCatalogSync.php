<?php

namespace App\Services\Sector;

use App\Models\Sector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use SplFileObject;

/**
 * Catálogo oficial de setores (CEIN) a partir de database/data/setores/centro-informacao-setor.csv.
 *
 * Decisão: setores da planilha são criados/reativados (casando por code ou name
 * para preservar IDs e vínculos). Setores fora da planilha NUNCA são apagados —
 * ficam active=false. Assim, caixa (sector_user) e processos (sector_id) não
 * somem em silêncio; o gestor vê o setor inativo e redistribui se preciso.
 */
class OfficialSectorCatalogSync
{
    public const RELATIVE_CSV = 'data/setores/centro-informacao-setor.csv';

    private const EXPECTED_HEADER = ['CEIN_NUMERO', 'CEIN_DESCRICAO'];

    /** Setor padrão de triagem em dev (caixa do motor) — presente no CSV oficial. */
    public const TRIAGEM_CODE = '0231400300';

    public const TRIAGEM_NAME = 'SUCOM-GERAE-STD-Setor de Triagem e Distribuição';

    /**
     * @return array{created: int, updated: int, reactivated: int, deactivated: int, total: int}
     */
    public function sync(?string $csvPath = null): array
    {
        // A migration de carga do catálogo roda antes de sectors.code existir.
        // A migration que cria a coluna é quem sincroniza o CSV oficial.
        if (! Schema::hasColumn((new Sector)->getTable(), 'code')) {
            return [
                'created' => 0,
                'updated' => 0,
                'reactivated' => 0,
                'deactivated' => 0,
                'total' => 0,
            ];
        }

        $path = $csvPath ?? database_path(self::RELATIVE_CSV);

        if (! is_readable($path)) {
            throw new RuntimeException("Planilha oficial de setores não encontrada: {$path}");
        }

        $rows = $this->readCsv($path);

        $created = 0;
        $updated = 0;
        $reactivated = 0;
        $deactivated = 0;
        $seenIds = [];

        DB::transaction(function () use ($rows, &$created, &$updated, &$reactivated, &$deactivated, &$seenIds) {
            foreach ($rows as $row) {
                $sector = $this->findEquivalent($row['code'], $row['name']);

                if ($sector === null) {
                    $sector = Sector::query()->create([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'active' => true,
                    ]);
                    $created++;
                    $seenIds[] = $sector->id;

                    continue;
                }

                $wasInactive = ! $sector->active;
                $dirty = $sector->code !== $row['code']
                    || $sector->name !== $row['name']
                    || $wasInactive;

                if ($dirty) {
                    $sector->fill([
                        'code' => $row['code'],
                        'name' => $row['name'],
                        'active' => true,
                    ])->save();
                    $updated++;

                    if ($wasInactive) {
                        $reactivated++;
                    }
                }

                $seenIds[] = $sector->id;
            }

            $deactivated = Sector::query()
                ->whereNotIn('id', $seenIds)
                ->where('active', true)
                ->update(['active' => false]);
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'reactivated' => $reactivated,
            'deactivated' => $deactivated,
            'total' => count($rows),
        ];
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    private function readCsv(string $path): array
    {
        $file = new SplFileObject($path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::READ_AHEAD | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $rows = [];

        foreach ($file as $line) {
            if ($line === false || $line === [null] || $line === null) {
                continue;
            }

            /** @var list<string|null> $line */
            if ($header === null) {
                $header = array_map(fn ($value) => trim((string) $value), $line);

                if ($header !== self::EXPECTED_HEADER) {
                    throw new RuntimeException(
                        'Cabeçalho inesperado na planilha de setores: esperado CEIN_NUMERO,CEIN_DESCRICAO.',
                    );
                }

                continue;
            }

            $code = trim((string) ($line[0] ?? ''));
            $name = trim((string) ($line[1] ?? ''));

            if ($code === '' || $name === '') {
                continue;
            }

            $rows[] = ['code' => $code, 'name' => $name];
        }

        if ($rows === []) {
            throw new RuntimeException('Planilha oficial de setores vazia.');
        }

        return $rows;
    }

    private function findEquivalent(string $code, string $name): ?Sector
    {
        $byCode = Sector::query()->where('code', $code)->first();

        if ($byCode !== null) {
            return $byCode;
        }

        return Sector::query()->where('name', $name)->first();
    }
}
