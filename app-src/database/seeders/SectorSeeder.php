<?php

namespace Database\Seeders;

use App\Models\Parameter;
use App\Models\Sector;
use Illuminate\Database\Seeder;

/**
 * Setor da SEDUR (HU-138) — a caixa de distribuição da análise técnica.
 *
 * O setor "Análise Locacional" é criado SEMPRE (catálogo padrão): em produção o
 * gestor mantém os setores pela interface (HU-138), mas um setor inicial evita
 * que a distribuição nasça bloqueada. Não cria usuários — os analistas reais
 * são cadastrados pelo administrador e vinculados pelo gestor.
 *
 * Idempotente: firstOrCreate por nome. Depende de RolesAndPermissionsSeeder
 * (papéis) e LegalTermSeeder (termo LGPD).
 */
class SectorSeeder extends Seeder
{
    public const SECTOR_NAME = 'Análise Locacional';

    public function run(): void
    {
        $sector = Sector::firstOrCreate(
            ['name' => self::SECTOR_NAME],
            ['active' => true],
        );

        // Elo motor → caixa do setor (analise.setor_triagem_id): o setor padrão
        // recebe os processos que o motor encaminha à análise. Só preenche
        // quando VAZIO — valor administrado pela interface (HU-014) é
        // preservado no re-seed.
        Parameter::query()
            ->where('key', 'analise.setor_triagem_id')
            ->whereNull('value')
            ->update(['value' => (string) $sector->id]);
    }
}
