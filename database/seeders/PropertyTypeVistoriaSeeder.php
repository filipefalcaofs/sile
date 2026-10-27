<?php

namespace Database\Seeders;

use App\Models\PropertyType;
use Illuminate\Database\Seeder;

/**
 * Tipos de imóvel que a ficha de vistoria passou a oferecer (SEDUR 2026-10-27).
 * Entram sem alias, sem gatilho e fora do reconhecimento do REGIN: ele continua sem reconhecê-los e o
 * encaminhamento do processo não muda. Idempotente e aditivo — não reativa nem
 * renomeia um tipo que a SEDUR já ajustou no cadastro.
 */
class PropertyTypeVistoriaSeeder extends Seeder
{
    public function run(): void
    {
        $itens = [
            'lote' => 'Lote',
            'box' => 'Box',
            'centro_comercial' => 'Centro comercial',
            'shopping' => 'Shopping',
            'outros' => 'Outros',
        ];

        foreach ($itens as $code => $label) {
            PropertyType::firstOrCreate(
                ['code' => $code],
                ['label' => $label, 'drives_rule' => false, 'regin_reconhece' => false, 'active' => true],
            );
        }
    }
}
