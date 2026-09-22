<?php

namespace Database\Seeders;

use App\Enums\RuleDomain;
use App\Models\LouosQuadro10Permissao;
use App\Models\RuleVersion;
use App\Models\Zona;
use Illuminate\Database\Seeder;

/**
 * Carga inicial do cadastro de zonas urbanísticas (parametrização 3.3): a
 * fonte de verdade da zona é a própria LOUOS — o cadastro nasce dos valores
 * distintos de `zona` da versão VIGENTE do Quadro 10, sem depender de
 * arquivo. Idempotente por codigo via firstOrCreate (padrão
 * GeoServerLayerSeeder): o re-seed em deploy só cria as zonas AUSENTES —
 * NUNCA reativa uma zona que o administrador desativou pela UI nem
 * sobrescreve nome/macrozona editados (HU-014). Na criação, `ativo` usa o
 * default da coluna (true) e `nome` nasce igual ao codigo (o admin edita
 * pela tela). Sem Quadro 10 vigente o cadastro fica VAZIO — honesto: não se
 * inventa zona sem fonte legal.
 */
class ZonaSeeder extends Seeder
{
    public function run(): void
    {
        $vigente = RuleVersion::vigente(RuleDomain::LouosQuadro10)->first();

        if ($vigente === null) {
            $this->command?->warn('ZonaSeeder: nenhuma versão vigente do Quadro 10 — cadastro de zonas permanece vazio até a carga da LOUOS.');

            return;
        }

        $zonas = LouosQuadro10Permissao::query()
            ->where('rule_version_id', $vigente->id)
            ->distinct()
            ->orderBy('zona')
            ->pluck('zona');

        foreach ($zonas as $zona) {
            Zona::firstOrCreate(['codigo' => $zona], ['nome' => $zona]);
        }
    }
}
