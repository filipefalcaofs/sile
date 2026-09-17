<?php

namespace Tests\Feature\Regin;

use App\Models\Parameter;
use App\Services\Regin\ReginIntegrationSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ReginIntegrationSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_desmarcado_usa_a_url_de_homologacao_configurada(): void
    {
        $this->gravar('integrations.regin.em_producao', 'boolean', '0');
        $this->gravar(
            'integrations.regin.url_homologacao',
            'string',
            'http://10.57.247.9:8080/api_integracao',
        );
        $this->gravar(
            'integrations.regin.url_producao',
            'string',
            'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
        );

        $settings = app(ReginIntegrationSettings::class);

        $this->assertFalse($settings->usingProduction());
        $this->assertSame(
            'http://10.57.247.9:8080/api_integracao',
            $settings->baseUrl(),
        );
    }

    public function test_marcado_usa_a_url_de_producao_configurada(): void
    {
        $this->gravar('integrations.regin.em_producao', 'boolean', '1');
        $this->gravar(
            'integrations.regin.url_homologacao',
            'string',
            'http://10.57.247.9:8080/api_integracao',
        );
        $this->gravar(
            'integrations.regin.url_producao',
            'string',
            'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
        );

        $settings = app(ReginIntegrationSettings::class);

        $this->assertTrue($settings->usingProduction());
        $this->assertSame(
            'http://regin.prefeitura.juceb.ba.gov.br:8080/api_integracao',
            $settings->baseUrl(),
        );
    }

    public function test_a_url_editada_no_painel_substitui_o_padrao(): void
    {
        $this->gravar('integrations.regin.em_producao', 'boolean', '0');
        $this->gravar(
            'integrations.regin.url_homologacao',
            'string',
            'http://regin.local:8080/api_integracao',
        );

        $this->assertSame(
            'http://regin.local:8080/api_integracao',
            app(ReginIntegrationSettings::class)->baseUrl(),
        );
    }

    private function gravar(string $key, string $type, string $value): void
    {
        Parameter::factory()->create([
            'key' => $key,
            'group' => 'integracoes',
            'type' => $type,
            'value' => $value,
            'default_value' => $value,
        ]);
    }
}
