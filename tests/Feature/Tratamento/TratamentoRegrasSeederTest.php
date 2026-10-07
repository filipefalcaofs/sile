<?php

namespace Tests\Feature\Tratamento;

use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoCondicionante;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\TratamentoRegraRamo;
use Database\Seeders\TratamentoRegrasSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TratamentoRegrasSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_carrega_a_planilha_20_08_26(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(32, TratamentoPergunta::query()->count());
        $this->assertSame(3114, TratamentoCnaeBinding::query()->count());
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
    }

    public function test_seeder_nao_reimporta_quando_a_planilha_ja_existe(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        TratamentoPergunta::query()->delete();

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(
            0,
            TratamentoPergunta::query()->count(),
            'Re-seed não deve apagar nem reimportar uma planilha já carregada.',
        );
        $this->assertSame(2850, TratamentoEnquadramento::query()->count());
    }

    public function test_seeder_completa_a_carga_quando_faltam_ramos_e_condicionantes(): void
    {
        $this->seed(TratamentoRegrasSeeder::class);

        // Reproduz o estado de produção do incidente VIA-2026-000149: versão
        // vigente carregada antes do regras-fluxo.csv existir — enquadramentos
        // presentes, ramos e condicionantes zerados.
        TratamentoRegraRamo::query()->delete();
        TratamentoCondicionante::query()->delete();

        $this->seed(TratamentoRegrasSeeder::class);

        $this->assertSame(233, TratamentoRegraRamo::query()->count(), 'Re-seed deve completar os ramos curados ausentes (191 dos CSVs e 42 da SEDUR).');
        $this->assertSame(32, TratamentoCondicionante::query()->count(), 'Re-seed deve completar as condicionantes ausentes.');
        $this->assertSame(2850, TratamentoEnquadramento::query()->count(), 'A carga existente não pode ser apagada na completude.');
        $this->assertSame(3114, TratamentoCnaeBinding::query()->count());
    }
}
