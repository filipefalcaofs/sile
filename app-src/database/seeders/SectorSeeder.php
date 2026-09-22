<?php

namespace Database\Seeders;

use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * Setor inicial da análise locacional.
 * Em homologação e produção o gestor mantém os demais setores pela interface.
 */
class SectorSeeder extends Seeder
{
    public const SECTOR_NAME = 'Análise Locacional';

    public function run(): void
    {
        Sector::firstOrCreate(
            ['name' => self::SECTOR_NAME],
            ['active' => true],
        );
    }
}
