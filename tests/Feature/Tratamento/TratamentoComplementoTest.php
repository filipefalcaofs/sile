<?php

namespace Tests\Feature\Tratamento;

use App\Models\DecisionText;
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

    private const REGRAS_COM_RAMO_DA_SEDUR = [9, 29, 30, 31, 33, 37, 38, 39, 40, 43, 53, 55];

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

    public function test_nenhuma_regra_fica_pendente_depois_das_respostas_da_sedur(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(0, TratamentoPendencia::query()->count());
    }

    public function test_pendencia_antiga_some_ao_semear_uma_versao_ja_carregada(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $versaoId = TratamentoRegraRamo::query()->where('regra', 40)->value('rule_version_id');

        foreach ([40, 43] as $regra) {
            TratamentoPendencia::query()->create([
                'rule_version_id' => $versaoId,
                'regra' => $regra,
                'motivo' => 'Aguardando a SEDUR: a planilha não traz as opções da pergunta 1 que esta regra cita.',
            ]);
        }

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(0, TratamentoPendencia::query()->count());
    }

    public function test_seed_recarrega_os_ramos_curados_que_faltam_na_versao_ja_carregada(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $versaoId = TratamentoRegraRamo::query()->where('regra', 40)->value('rule_version_id');
        TratamentoRegraRamo::query()->whereIn('regra', [40, 43])->delete();
        foreach ([40, 43] as $regra) {
            TratamentoPendencia::query()->create(['rule_version_id' => $versaoId, 'regra' => $regra, 'motivo' => 'Aguardando a SEDUR.']);
        }

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(0, TratamentoPendencia::query()->count());
        foreach ([40, 43] as $regra) {
            $this->assertGreaterThan(0, TratamentoRegraRamo::query()->where('regra', $regra)->whereNotNull('condicoes')->count());
        }
    }

    public function test_regras_respondidas_pela_sedur_ganham_ramo_curado(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        foreach (self::REGRAS_COM_RAMO_DA_SEDUR as $regra) {
            $this->assertGreaterThan(
                0,
                TratamentoRegraRamo::query()->where('regra', $regra)->whereNotNull('condicoes')->count(),
                "A regra {$regra} ficou sem ramo curado.",
            );
        }
    }

    public function test_seed_registra_os_textos_das_pendencias_e_dos_motivos_para_edicao(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        foreach ([
            'tratamento.pendencia.sem_fluxo_curado',
            'tratamento.motivo.cnlu',
            'tratamento.bloqueio.so_orgao_publico',
            'expresso.motivo.veredito_pendente',
        ] as $chave) {
            $texto = DecisionText::query()->where('key', $chave)->first();

            $this->assertNotNull($texto, "A chave {$chave} não foi registrada para edição.");
            $this->assertStringStartsNotWith('Texto decisório ', (string) $texto->description, "A chave {$chave} ficou com descrição genérica.");
        }
    }

    public function test_ramos_antigos_das_regras_curadas_pela_sedur_saem_do_caminho(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        foreach ([9, 33] as $regra) {
            $this->assertSame(
                0,
                TratamentoRegraRamo::query()->where('regra', $regra)->whereNull('condicoes')->count(),
                "A regra {$regra} ficou com ramo antigo, que lê a pergunta 1 como Sim/Não.",
            );
        }
    }

    public function test_seed_repetido_nao_duplica_nem_apaga(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);
        $ramos = TratamentoRegraRamo::query()->count();

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
        $this->assertSame(15, TratamentoCnluItem::query()->count());
        $this->assertSame(0, TratamentoPendencia::query()->count());
        $this->assertSame($ramos, TratamentoRegraRamo::query()->count());
    }
}
