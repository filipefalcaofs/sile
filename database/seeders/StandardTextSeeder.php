<?php

namespace Database\Seeders;

use App\Models\StandardText;
use Illuminate\Database\Seeder;

/**
 * Biblioteca de textos-padrão do parecer (HU-085) — trechos pré-aprovados que o
 * analista insere ao redigir o parecer, agrupados por categoria. São EXEMPLOS de
 * fundamentação (dados versionados, não código): a SEDUR mantém/substitui pela
 * interface sem deploy, evoluindo a versão e preservando o histórico.
 *
 * Criados SEMPRE (catálogo inicial), em pt-BR, na versão 1 e ativos. Idempotente:
 * firstOrCreate por categoria+conteúdo — re-seed não duplica nem reescreve.
 */
class StandardTextSeeder extends Seeder
{
    /**
     * @var list<array{category: string, content: string}>
     */
    private const TEXTS = [
        [
            'category' => 'deferimento',
            'content' => 'A viabilidade locacional é DEFERIDA: a atividade é admitida na zona, nos termos do Quadro 10 da LOUOS (Lei nº 9.148/2016), atendidos os parâmetros de enquadramento do Quadro 7.',
        ],
        [
            'category' => 'indeferimento',
            'content' => 'A viabilidade locacional é INDEFERIDA: a atividade não é admitida na zona, conforme o Quadro 10 da LOUOS (Lei nº 9.148/2016).',
        ],
        [
            'category' => 'condicionante',
            'content' => 'O deferimento fica condicionado ao atendimento das condicionantes urbanísticas aplicáveis (LOUOS, Lei nº 9.148/2016) antes do início efetivo da atividade.',
        ],
        [
            'category' => 'pendencia',
            'content' => 'Solicita-se complementação documental/esclarecimento para a continuidade da análise técnica, nos termos do regramento da SEDUR.',
        ],
    ];

    public function run(): void
    {
        foreach (self::TEXTS as $text) {
            StandardText::firstOrCreate(
                ['category' => $text['category'], 'content' => $text['content']],
                ['active' => true, 'version' => 1],
            );
        }
    }
}
