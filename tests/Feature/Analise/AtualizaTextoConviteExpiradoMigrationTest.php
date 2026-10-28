<?php

namespace Tests\Feature\Analise;

use App\Models\DecisionText;
use App\Services\Decisao\DecisionTextCatalog;
use Database\Seeders\DecisionTextSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Texto padrão da SEDUR (23/10/2026) para o indeferimento automático por
 * convite não atendido no prazo: bancos já seedados com o texto antigo
 * recebem o novo; o que a gestão editou fica intacto.
 */
class AtualizaTextoConviteExpiradoMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CHAVE = 'justificativa.indeferimento.convite_expirado';

    private const ANTIGO = 'Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020. Recomenda-se a abertura de novo processo contendo toda a documentação solicitada.';

    private const NOVO = "Processo indeferido pelo não atendimento ao convite no prazo estabelecido em consonância com Lei Federal nº 11.598/2007 e Resolução CGSIM nº 61/2020.\n\nRecomenda-se a abertura de novo processo contendo toda a documentação/ esclarecimento solicitado(s).";

    private function migration(): object
    {
        return require database_path('migrations/2026_10_23_150237_atualiza_texto_indeferimento_convite_expirado.php');
    }

    public function test_texto_de_fabrica_e_o_padrao_da_sedur(): void
    {
        $this->assertSame(self::NOVO, DecisionTextCatalog::defaults()[self::CHAVE]);
    }

    public function test_troca_o_texto_antigo_e_volta_no_down(): void
    {
        $this->seed(DecisionTextSeeder::class);
        DecisionText::query()->where('key', self::CHAVE)->update(['template' => self::ANTIGO]);

        $this->migration()->up();

        $this->assertSame(self::NOVO, DecisionText::query()->where('key', self::CHAVE)->value('template'));

        $this->migration()->down();

        $this->assertSame(self::ANTIGO, DecisionText::query()->where('key', self::CHAVE)->value('template'));
    }

    public function test_preserva_texto_editado_pela_gestao(): void
    {
        $this->seed(DecisionTextSeeder::class);
        DecisionText::query()->where('key', self::CHAVE)->update(['template' => 'Texto revisado pela gestão']);

        $this->migration()->up();

        $this->assertSame('Texto revisado pela gestão', DecisionText::query()->where('key', self::CHAVE)->value('template'));
    }
}
