<?php

namespace Tests\Feature\Analise;

use App\Models\AnalysisRecord;
use App\Models\StandardText;
use App\Services\Analise\StandardTextComposer;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class StandardTextComposerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_costura_texto_padrao_ativo_com_fatos_do_motor(): void
    {
        StandardText::factory()->create([
            'category' => 'deferimento',
            'content' => 'Deferimento do CNAE {{cnae}} com veredito {{veredito}}.',
            'active' => true,
        ]);
        StandardText::factory()->inativo()->create([
            'category' => 'deferimento',
            'content' => 'Não deve entrar.',
        ]);

        $ficha = AnalysisRecord::factory()->create([
            'engine_snapshot' => [
                'consolidado' => 'permitido',
                'ponto' => ['lat' => -12.97, 'lng' => -38.51],
            ],
            'per_cnae' => [[
                'cnae' => '4712100',
                'cnae_formatado' => '4712-1/00',
                'status_sugerido' => 'deferida',
            ]],
        ]);

        $texto = app(StandardTextComposer::class)->paraFicha($ficha);

        $this->assertStringContainsString('Deferimento do CNAE 4712-1/00 com veredito permitido.', $texto);
        $this->assertStringNotContainsString('Não deve entrar.', $texto);
    }
}
