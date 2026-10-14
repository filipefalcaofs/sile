<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Correções de citação legal conferidas na fonte oficial (minuta de
 * fundamentação de 14/10/2026, seção 7): regras territoriais, textos
 * decisórios de fábrica e base legal dos Quadros 10 e 11A já carregados.
 * Também liga as observações (a)/(b) do Quadro 10 nas cargas oficiais, que
 * gravaram o marcador só em condicionante_ref.
 *
 * Só troca o que ainda está com o texto antigo — edições feitas pela gestão
 * são preservadas. Fresh installs já nascem certos pelos seeders.
 * Idempotente e reversível.
 */
return new class extends Migration
{
    private const RESIDENCIAL_ANTIGO = 'não é permitida em imóvel residencial conforme Decreto Municipal nº 29.987/2018 que trata do funcionamento e estabelecimento de microempresas, empresas de pequeno e MEIs nas residências de seus titulares.';

    private const RESIDENCIAL_PLURAL_ANTIGO = 'não é (são) permitida(s) em imóvel residencial conforme Decreto Municipal nº 29.987/2018 que trata do funcionamento e estabelecimento de microempresas, empresas de pequeno e MEIs nas residências de seus titulares.';

    public function up(): void
    {
        foreach ($this->trocas() as $troca) {
            $this->trocar($troca, $troca['antigo'], $troca['novo']);
        }

        foreach (['a', 'b'] as $nota) {
            DB::table('louos_quadro10_permissoes')
                ->where('condicionante_ref', "Observação ({$nota}) do Quadro 10 da Lei nº 9.148/2016")
                ->whereNull('observacao')
                ->update(['observacao' => "({$nota})", 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach ($this->trocas() as $troca) {
            $this->trocar($troca, $troca['novo'], $troca['antigo']);
        }

        foreach (['a', 'b'] as $nota) {
            DB::table('louos_quadro10_permissoes')
                ->where('condicionante_ref', "Observação ({$nota}) do Quadro 10 da Lei nº 9.148/2016")
                ->where('observacao', "({$nota})")
                ->update(['observacao' => null, 'updated_at' => now()]);
        }
    }

    /**
     * @param  array{tabela: string, coluna: string, filtro: array<string, string>}  $troca
     */
    private function trocar(array $troca, string $de, string $para): void
    {
        DB::table($troca['tabela'])
            ->where($troca['filtro'])
            ->where($troca['coluna'], $de)
            ->update([$troca['coluna'] => $para, 'updated_at' => now()]);
    }

    /**
     * @return list<array{tabela: string, coluna: string, filtro: array<string, string>, antigo: string, novo: string}>
     */
    private function trocas(): array
    {
        $regra = fn (string $codigo, string $coluna, string $antigo, string $novo): array => [
            'tabela' => 'territorial_rules',
            'coluna' => $coluna,
            'filtro' => ['codigo' => $codigo],
            'antigo' => $antigo,
            'novo' => $novo,
        ];

        $texto = fn (string $key, string $antigo, string $novo): array => [
            'tabela' => 'decision_texts',
            'coluna' => 'template',
            'filtro' => ['key' => $key],
            'antigo' => $antigo,
            'novo' => $novo,
        ];

        $tac = 'art. 18, §3º, da Lei Municipal nº 9.148/2016 (LOUOS), combinado com o art. 162 da Lei Municipal nº 9.069/2016 (PDDU)';

        return [
            $regra('RT-01', 'base_legal',
                'art. 172-A da Lei Municipal nº 9.148/2016, com redação dada pela Lei Municipal nº 9.879/2025',
                'art. 172-A da Lei Municipal nº 9.148/2016 (LOUOS), incluído pelo art. 11 da Lei Municipal nº 9.879/2025'),
            $regra('RT-02-VELA-BRANCA', 'base_legal', '§3º do art. 162 do PDDU', $tac),
            $regra('RT-02-ITAIGARA', 'base_legal', '§3º do art. 162 do PDDU', $tac),
            $regra('RT-03-ZEM', 'base_legal',
                'Lei Municipal nº 9.148/2016 (LOUOS)',
                'art. 31, caput e §2º, da Lei Municipal nº 9.148/2016 (LOUOS)'),
            $regra('RT-03-ZPAM', 'base_legal',
                'Lei Municipal nº 9.148/2016 (LOUOS)',
                'art. 33, §4º, da Lei Municipal nº 9.148/2016 (LOUOS)'),
            $regra('RT-03-ABM', 'motivo_analise',
                'Solicitação cadastrada nos Trechos 5 e 6 da AMB – Área de Borda Marítima, em conformidade com as disposições estabelecidas pelo Decreto Municipal nº 31.168/2019.',
                'Solicitação cadastrada nos Trechos 5 e 6 da ABM – Área de Borda Marítima, em conformidade com as disposições estabelecidas pelo Decreto Municipal nº 31.168/2019.'),
            $regra('RT-04-ZUE', 'titulo',
                'Zonas de Uso Especial (art. 32, ZUE-1 a ZUE-13)',
                'Zonas de Uso Especial (art. 32, ZUE-1 a ZUE-17; incisos XIV a XVII incluídos pela Lei nº 9.879/2025)'),
            $regra('RT-04-ZUE', 'base_legal',
                'Art. 32 da Lei Municipal nº 9.148/2016 (LOUOS)',
                'art. 32, §3º, da Lei Municipal nº 9.148/2016 (LOUOS)'),
            $texto('base_legal.risco_municipal',
                'Decreto Municipal nº 41.758/2026',
                'Decreto Municipal nº 32.636/2020, com os Anexos na redação do Decreto Municipal nº 41.758/2026'),
            $texto('tratamento.bloqueio.residencial',
                'A atividade solicitada '.self::RESIDENCIAL_ANTIGO,
                'A atividade solicitada '.$this->residencialNovo(self::RESIDENCIAL_ANTIGO)),
            $texto('tratamento.bloqueio.residencial_plural',
                'A (s) atividade (s) solicitada (s) '.self::RESIDENCIAL_PLURAL_ANTIGO,
                'A (s) atividade (s) solicitada (s) '.$this->residencialNovo(self::RESIDENCIAL_PLURAL_ANTIGO)),
            [
                'tabela' => 'louos_quadro10_permissoes',
                'coluna' => 'base_legal',
                'filtro' => [],
                'antigo' => 'Lei nº 9.148/2016 — Quadro 10',
                'novo' => 'art. 116, I, e Quadro 10 do Anexo 01 da Lei nº 9.148/2016',
            ],
            [
                'tabela' => 'louos_quadro11_condicoes_via',
                'coluna' => 'base_legal',
                'filtro' => [],
                'antigo' => 'Lei nº 9.148/2016 — Quadro 11A',
                'novo' => 'art. 116, II, e Quadro 11-A do Anexo 01 da Lei nº 9.148/2016',
            ],
        ];
    }

    private function residencialNovo(string $antigo): string
    {
        return str_replace(
            'conforme Decreto Municipal nº 29.987/2018 que trata',
            'conforme o art. 4º do Decreto Municipal nº 29.987/2018, que trata',
            $antigo,
        );
    }
};
