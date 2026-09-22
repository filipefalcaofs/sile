<?php

namespace Tests\Feature\Realty;

use App\Models\Parameter;
use App\Services\Realty\InscricaoImobiliariaIntegrationSettings;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InscricaoImobiliariaIntegrationSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_desmarcado_usa_a_url_de_homologacao_configurada(): void
    {
        $this->gravar('integrations.inscricao_imobiliaria.em_producao', 'boolean', '0');
        $this->gravar(
            'integrations.inscricao_imobiliaria.url_homologacao',
            'string',
            'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
        );
        $this->gravar(
            'integrations.inscricao_imobiliaria.url_producao',
            'string',
            'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
        );

        $settings = app(InscricaoImobiliariaIntegrationSettings::class);

        $this->assertFalse($settings->usingProduction());
        $this->assertSame(
            'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            $settings->baseUrl(),
        );
    }

    public function test_marcado_usa_a_url_de_producao_configurada(): void
    {
        $this->gravar('integrations.inscricao_imobiliaria.em_producao', 'boolean', '1');
        $this->gravar(
            'integrations.inscricao_imobiliaria.url_homologacao',
            'string',
            'https://api.sedur.salvador.ba.gov.br/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
        );
        $this->gravar(
            'integrations.inscricao_imobiliaria.url_producao',
            'string',
            'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
        );

        $settings = app(InscricaoImobiliariaIntegrationSettings::class);

        $this->assertTrue($settings->usingProduction());
        $this->assertSame(
            'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            $settings->baseUrl(),
        );
    }

    public function test_a_url_editada_no_painel_substitui_o_padrao(): void
    {
        $this->gravar('integrations.inscricao_imobiliaria.em_producao', 'boolean', '0');
        $this->gravar(
            'integrations.inscricao_imobiliaria.url_homologacao',
            'string',
            'https://api.sedur.local/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
        );

        $this->assertSame(
            'https://api.sedur.local/k8s/hml/ws-bff-portal-servicos/v1/inscricao-imobiliaria',
            app(InscricaoImobiliariaIntegrationSettings::class)->baseUrl(),
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
