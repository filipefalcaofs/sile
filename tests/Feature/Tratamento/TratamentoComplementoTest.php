<?php

namespace Tests\Feature\Tratamento;

use App\Models\TratamentoCnluItem;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoNota;
use App\Models\TratamentoPendencia;
use App\Models\TratamentoRegraRamo;
use Database\Seeders\TratamentoRegrasSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * O seed da versão vigente completa a carga dos CSVs com o que só a planilha
 * xlsx traz: metadados e vagas por enquadramento, lista CNLU, notas gerais e
 * as regras que aguardam a SEDUR.
 */
class TratamentoComplementoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const REGRAS_AGUARDANDO = [29, 30, 31, 37, 38, 39, 40, 41, 42, 43, 53, 55];

    public function test_todo_enquadramento_recebe_metadados_e_vagas_da_planilha(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        $this->assertSame(0, TratamentoEnquadramento::query()->whereNull('metadados')->count());

        $linha = TratamentoEnquadramento::query()->orderBy('id')->firstOrFail();

        $this->assertSame('2024-04-12', $linha->metadados['atualizado_em']);
        $this->assertArrayHasKey('carga_1_aplica', $linha->vagas);
        $this->assertArrayHasKey('embarque_3_analise', $linha->vagas);
    }

    public function test_lista_cnlu_carrega_no_nivel_de_subgrupo(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $codigos = TratamentoCnluItem::query()->orderBy('codigo')->pluck('codigo')->all();

        $this->assertSame(
            [
                'ID3-01', 'ID3-02', 'ID3-03', 'ID3-04', 'ID3-05', 'ID3-07', 'ID3-13', 'ID3-14', 'ID3-17', 'ID3-19', 'ID3-21',
                'nR3-08', 'nR3-14', 'nR4', 'nRa-02',
            ],
            $codigos,
        );
        $this->assertFalse(TratamentoNota::query()->where('chave', 'cnlu_aguardando_sedur')->exists());
    }

    public function test_regras_sem_fluxo_curado_ficam_como_pendencia_da_sedur(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(
            self::REGRAS_AGUARDANDO,
            TratamentoPendencia::query()->orderBy('regra')->pluck('regra')->all(),
        );
        $this->assertStringStartsWith(
            'Aguardando confirmação da SEDUR',
            (string) TratamentoPendencia::query()->where('regra', 38)->value('motivo'),
        );
        $this->assertSame(0, TratamentoRegraRamo::query()->whereIn('regra', self::REGRAS_AGUARDANDO)->count());
    }

    public function test_seed_repetido_nao_duplica_nem_apaga(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);
        $ramos = TratamentoRegraRamo::query()->count();

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        $this->assertSame(15, TratamentoCnluItem::query()->count());
        $this->assertSame(12, TratamentoPendencia::query()->count());
        $this->assertSame($ramos, TratamentoRegraRamo::query()->count());
    }
}
