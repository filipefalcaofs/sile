<?php

namespace Tests\Feature\Decisao;

use App\Models\DecisionText;
use App\Services\Decisao\DecisionTextCatalog;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Tests\TestCase;

class DecisionTextCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_get_devolve_o_texto_da_chave_seedada(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $catalogo = new DecisionTextCatalog;

        $this->assertSame(
            'Atividade proibida na zona pelo Quadro 10',
            $catalogo->get('louos.motivo.proibido'),
        );
    }

    public function test_get_cai_nos_defaults_quando_o_banco_nao_tem_a_chave(): void
    {
        // Banco alcançável e vazio (sem seed): o texto de fábrica mantém o
        // motor funcionando — a edição administrativa só existe após o seed.
        $catalogo = new DecisionTextCatalog;

        $this->assertSame('Lei nº 9.148/2016 (LOUOS)', $catalogo->get('base_legal.louos'));
    }

    public function test_render_interpola_os_placeholders(): void
    {
        $catalogo = new DecisionTextCatalog;

        $this->assertSame(
            'O grupo A1 é permitido na zona ZEC segundo o Quadro 10 da LOUOS — é este quadro que permite ou proíbe o uso no território.',
            $catalogo->render('louos.template.quadro10', [
                ':grupo' => 'A1',
                ':permissao' => 'permitido',
                ':zona' => 'ZEC',
            ]),
        );
    }

    public function test_render_deixa_literal_o_placeholder_nao_fornecido(): void
    {
        $catalogo = new DecisionTextCatalog;

        $texto = $catalogo->render('louos.template.quadro10', [':grupo' => 'A1']);

        $this->assertStringContainsString(':permissao', $texto);
        $this->assertStringContainsString(':zona', $texto);
    }

    public function test_get_de_chave_desconhecida_lanca_excecao(): void
    {
        $this->seed(DecisionTextSeeder::class);

        $this->expectException(InvalidArgumentException::class);

        (new DecisionTextCatalog)->get('chave.que.nao.existe');
    }

    public function test_escrita_no_model_invalida_o_cache(): void
    {
        $texto = DecisionText::query()->create([
            'key' => 'base_legal.louos',
            'template' => 'Texto original',
            'description' => 'Teste de invalidação do cache na escrita',
        ]);

        $catalogo = new DecisionTextCatalog;

        $this->assertSame('Texto original', $catalogo->get('base_legal.louos'));
        $this->assertTrue(Cache::has(DecisionText::CACHE_KEY));

        $texto->update(['template' => 'Texto editado pela gestão']);

        $this->assertFalse(Cache::has(DecisionText::CACHE_KEY));
        $this->assertSame('Texto editado pela gestão', $catalogo->get('base_legal.louos'));
    }

    public function test_remocao_no_model_invalida_o_cache(): void
    {
        $texto = DecisionText::query()->create([
            'key' => 'base_legal.louos',
            'template' => 'Texto original',
            'description' => 'Teste de invalidação do cache na remoção',
        ]);

        $this->assertSame('Texto original', (new DecisionTextCatalog)->get('base_legal.louos'));
        $this->assertTrue(Cache::has(DecisionText::CACHE_KEY));

        $texto->delete();

        $this->assertFalse(Cache::has(DecisionText::CACHE_KEY));
    }
}
