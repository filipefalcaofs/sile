<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro11CondicaoVia;
use App\Models\RuleVersion;
use App\Models\Via;
use Illuminate\Database\Seeder;

/**
 * Carga inicial do cadastro de classes de via da LOUOS (relatório de
 * usabilidade SEDUR 19/09/2026, item 07): a fonte de verdade é a própria
 * LOUOS — o cadastro nasce dos valores distintos de `classe_via` da versão
 * VIGENTE do Quadro 11A, sem depender de arquivo. Idempotente por codigo via
 * firstOrCreate (padrão ZonaSeeder): o re-seed em deploy só cria as vias
 * AUSENTES — NUNCA reativa uma via que o administrador desativou pela UI nem
 * sobrescreve o nome editado (HU-014). Na criação, `ativo` usa o default da
 * coluna (true) e `nome` nasce igual ao codigo (o admin edita pela tela).
 * Sem Quadro 11A vigente o cadastro fica VAZIO — honesto: não se inventa
 * classe de via sem fonte legal.
 */
class ViaSeeder extends Seeder
{
    public function run(): void
    {
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro11a)->first();

        if ($vigente === null) {
            $this->command?->warn('ViaSeeder: nenhuma versão vigente do Quadro 11A — cadastro de vias permanece vazio até a carga da LOUOS.');

            return;
        }

        $vias = LouosQuadro11CondicaoVia::query()
            ->where('rule_version_id', $vigente->id)
            ->distinct()
            ->orderBy('classe_via')
            ->pluck('classe_via');

        foreach ($vias as $via) {
            Via::firstOrCreate(['codigo' => $via], ['nome' => $via]);
        }
    }
}
