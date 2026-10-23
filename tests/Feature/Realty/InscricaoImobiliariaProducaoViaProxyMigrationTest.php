<?php

namespace Tests\Feature\Realty;

use App\Models\Parameter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class InscricaoImobiliariaProducaoViaProxyMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ANTIGA = 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-bff-portal-servicos/v1/inscricao-imobiliaria';

    private const NOVA = 'https://api.sedur.salvador.ba.gov.br/k8s/prd/ws-proxy-sefaz/integra/v1/inscricao-imobiliaria?valor=';

    public function test_troca_a_url_de_fabrica_e_preserva_a_editada_pela_gestao(): void
    {
        Parameter::query()->where('key', 'like', 'integrations.inscricao_imobiliaria.%')->delete();

        foreach ([
            'integrations.inscricao_imobiliaria.url_producao' => self::ANTIGA,
            'integrations.inscricao_imobiliaria.base_url' => 'https://api.sedur.local/editada',
        ] as $key => $value) {
            Parameter::factory()->create([
                'key' => $key,
                'group' => 'integracoes',
                'type' => 'string',
                'value' => $value,
                'default_value' => self::ANTIGA,
            ]);
        }

        $migration = require database_path('migrations/2026_10_23_180000_inscricao_imobiliaria_producao_via_proxy_sefaz.php');
        $migration->up();

        $producao = Parameter::query()->where('key', 'integrations.inscricao_imobiliaria.url_producao')->first();
        $this->assertSame(self::NOVA, $producao->value);
        $this->assertSame(self::NOVA, $producao->default_value);
        $this->assertSame(
            'https://api.sedur.local/editada',
            Parameter::query()->where('key', 'integrations.inscricao_imobiliaria.base_url')->value('value'),
        );

        $migration->down();

        $this->assertSame(
            self::ANTIGA,
            Parameter::query()->where('key', 'integrations.inscricao_imobiliaria.url_producao')->value('value'),
        );
    }
}
