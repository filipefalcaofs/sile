<?php

namespace Tests\Feature\Geo;

use Tests\TestCase;

/**
 * Validação de entrada do comando geo:importar (HU-036). As rejeições
 * acontecem ANTES de qualquer carga espacial, então rodam em SQLite (sem
 * PostGIS) — a carga real é provada em ImportGeoLayerTest (@group postgis).
 */
class ImportGeoLayerCommandTest extends TestCase
{
    public function test_tipo_de_camada_invalido_falha_com_mensagem(): void
    {
        $this->artisan('geo:importar', [
            'type' => 'inexistente',
            'arquivo' => base_path('tests/Fixtures/geo/bairros-amostra.geojson'),
            '--versao' => 'v1',
        ])
            ->expectsOutputToContain('Tipo de camada inválido')
            ->assertFailed();
    }

    public function test_arquivo_inexistente_falha(): void
    {
        $this->artisan('geo:importar', [
            'type' => 'bairro',
            'arquivo' => base_path('tests/Fixtures/geo/nao-existe.geojson'),
            '--versao' => 'v1',
        ])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertFailed();
    }

    public function test_versao_ausente_falha(): void
    {
        $this->artisan('geo:importar', [
            'type' => 'bairro',
            'arquivo' => base_path('tests/Fixtures/geo/bairros-amostra.geojson'),
        ])
            ->expectsOutputToContain('Informe a versão')
            ->assertFailed();
    }

    public function test_json_invalido_falha(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'geo').'.json';
        file_put_contents($path, '{ invalido');

        try {
            $this->artisan('geo:importar', [
                'type' => 'bairro',
                'arquivo' => $path,
                '--versao' => 'v1',
            ])
                ->expectsOutputToContain('JSON inválido')
                ->assertFailed();
        } finally {
            @unlink($path);
        }
    }
}
