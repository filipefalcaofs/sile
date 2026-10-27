<?php

namespace Tests\Feature\Servicos;

use App\Models\ServicoNomenclatura;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Documento da SEDUR de 23/10/2026: "Revisão de Endereço" passa a "Alteração
 * de Endereço" e a linha de espaço fica "Revisão Alteração de Espaço". Nome
 * editado na tela do De x Para não é tocado.
 */
class RenomeiaRevisaoDeEnderecoMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENTRE_ESTADOS = 'alteracao de endereco entre estados';

    private const MESMO_MUNICIPIO = 'alteracao de endereco dentro do mesmo municipio';

    private function migration(): object
    {
        return require database_path('migrations/2026_10_26_163306_rename_revisao_de_endereco_in_servico_nomenclaturas.php');
    }

    private function para(string $chave, string $condicao = ''): ?string
    {
        return ServicoNomenclatura::query()->where('chave', $chave)->where('condicao', $condicao)->value('para');
    }

    /**
     * A migration de 27/10 renomeia "Revisão Alteração de Espaço" para "Revisão de
     * Espaço"; este teste parte do nome que a migration de 26/10 deixou.
     */
    private function voltarAoNomeDestaMigration(): void
    {
        ServicoNomenclatura::query()->where('para', 'Revisão de Espaço')->update(['para' => 'Revisão Alteração de Espaço']);
    }

    public function test_renomeia_os_nomes_de_fabrica_e_volta_no_down(): void
    {
        $this->voltarAoNomeDestaMigration();
        $this->migration()->down();

        $this->assertSame('Revisão de Endereço', $this->para(self::ENTRE_ESTADOS));
        $this->assertSame('Revisão de Endereço', $this->para(self::MESMO_MUNICIPIO, 'endereco_localizacao'));
        $this->assertSame('Revisão de Endereço - Alteração de Espaço', $this->para(self::MESMO_MUNICIPIO, 'endereco_espaco'));

        $this->migration()->up();

        $this->assertSame('Alteração de Endereço', $this->para(self::ENTRE_ESTADOS));
        $this->assertSame('Alteração de Endereço', $this->para(self::MESMO_MUNICIPIO, 'endereco_localizacao'));
        $this->assertSame('Revisão Alteração de Espaço', $this->para(self::MESMO_MUNICIPIO, 'endereco_espaco'));
        $this->assertSame(0, ServicoNomenclatura::query()->where('para', 'Revisão de Endereço')->count());
    }

    public function test_preserva_nome_editado_na_tela(): void
    {
        $this->voltarAoNomeDestaMigration();
        $this->migration()->down();
        ServicoNomenclatura::query()->where('chave', self::ENTRE_ESTADOS)->update(['para' => 'Mudança de Estado']);
        ServicoNomenclatura::query()->where('chave', self::MESMO_MUNICIPIO)->where('condicao', 'endereco_espaco')->update(['para' => 'Troca de Sala']);

        $this->migration()->up();

        $this->assertSame('Mudança de Estado', $this->para(self::ENTRE_ESTADOS));
        $this->assertSame('Troca de Sala', $this->para(self::MESMO_MUNICIPIO, 'endereco_espaco'));
        $this->assertSame('Alteração de Endereço', $this->para(self::MESMO_MUNICIPIO, 'endereco_localizacao'));
    }
}
