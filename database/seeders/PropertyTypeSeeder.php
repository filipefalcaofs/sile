<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use App\Services\Risco\TipoImovel;
use Illuminate\Database\Seeder;

/**
 * Carga inicial = catálogo SEDUR 2026-08-26 (hoje TipoImovelCatalog::sedur200826()).
 * Idempotente e aditivo: updateOrCreate por code, firstOrCreate por alias.
 */
class PropertyTypeSeeder extends Seeder
{
    public function run(): void
    {
        $itens = [
            ['code' => 'galpao', 'label' => 'Galpão', 'drives_rule' => true, 'aliases' => ['galpao']],
            ['code' => 'container', 'label' => 'Container', 'drives_rule' => true, 'aliases' => ['container']],
            ['code' => 'edificacao_residencial', 'label' => 'Edificação residencial', 'drives_rule' => true, 'aliases' => ['edificacao residencial']],
            ['code' => 'edificacao_comercial', 'label' => 'Edificação comercial', 'drives_rule' => false, 'aliases' => ['edificacao comercial']],
            ['code' => 'sala', 'label' => 'Sala', 'drives_rule' => false, 'aliases' => ['sala']],
        ];

        foreach ($itens as $item) {
            $tipo = PropertyType::updateOrCreate(
                ['code' => $item['code']],
                ['label' => $item['label'], 'drives_rule' => $item['drives_rule'], 'active' => true],
            );

            foreach ($item['aliases'] as $alias) {
                // O model normaliza na gravação, mas a BUSCA do firstOrCreate
                // compara o valor cru: normalizar aqui mantém a idempotência
                // caso um alias acentuado entre nesta lista.
                $tipo->aliases()->firstOrCreate(['alias' => TipoImovel::normalize($alias)]);
            }
        }
    }
}
