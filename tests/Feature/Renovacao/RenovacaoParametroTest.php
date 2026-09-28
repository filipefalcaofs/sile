<?php

namespace Tests\Feature\Renovacao;

use App\Support\Settings;
use Database\Seeders\ParameterSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RenovacaoParametroTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_intervalo_padrao_entre_renovacoes_e_tres_meses(): void
    {
        $this->seed(ParameterSeeder::class);

        $this->assertSame(3, Settings::get('renovacao.intervalo_meses'));
    }
}
