<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            CatalogoOficialSeeder::class,
            // SMTP da operação. Credencial de ambiente, não dado do motor:
            // a migration de homologação não planta esta senha.
            EmailServerSeeder::class,
        ]);

        if (class_exists(DevDatabaseSeeder::class)) {
            $this->call(DevDatabaseSeeder::class);
        }
    }
}
