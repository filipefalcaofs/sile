<?php

namespace Tests\Unit;

use Tests\TestCase;

class VersaoDaAplicacaoTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv('APP_VERSION');

        parent::tearDown();
    }

    private function versaoDoArquivo(): string
    {
        return trim((string) file_get_contents(dirname(__DIR__, 2).'/VERSION'));
    }

    private function versaoConfigurada(): string
    {
        $config = require dirname(__DIR__, 2).'/config/app.php';

        return $config['version'];
    }

    public function test_arquivo_version_vale_mesmo_com_variavel_de_ambiente_diferente(): void
    {
        putenv('APP_VERSION=v0.30.0');

        $this->assertSame($this->versaoDoArquivo(), $this->versaoConfigurada());
    }

    public function test_versao_nao_comeca_com_v(): void
    {
        putenv('APP_VERSION=v0.30.0');

        $this->assertDoesNotMatchRegularExpression('/^v/i', $this->versaoConfigurada());
    }
}
