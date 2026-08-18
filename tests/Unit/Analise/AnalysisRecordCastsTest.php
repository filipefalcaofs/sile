<?php

namespace Tests\Unit\Analise;

use App\Models\AnalysisRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalysisRecordCastsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_reasons_e_address_confirmed_sao_persistidos_e_castados(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'analysis_reasons' => ['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'],
            'address_confirmed' => false,
        ]);

        $ficha->refresh();

        $this->assertSame(['Área zoneamento Semi expresso', 'Tipo Espaço - Casa'], $ficha->analysis_reasons);
        $this->assertFalse($ficha->address_confirmed);
    }

    public function test_analysis_reasons_e_address_confirmed_aceitam_null(): void
    {
        $ficha = AnalysisRecord::factory()->create([
            'analysis_reasons' => null,
            'address_confirmed' => null,
        ]);

        $ficha->refresh();

        $this->assertNull($ficha->analysis_reasons);
        $this->assertNull($ficha->address_confirmed);
    }
}
