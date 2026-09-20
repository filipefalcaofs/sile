<?php

namespace Tests\Feature\Tratamento;

use App\Models\TratamentoCnaeBinding;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
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
        $this->assertSame(2850, TratamentoCnaeBinding::query()->count());
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
}
