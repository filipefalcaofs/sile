<?php

namespace Tests\Feature\Companies;

use App\Models\Cnae;
use App\Models\Company;
use App\Services\RedesimImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RedesimImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Escreve os itens num arquivo JSON temporário e retorna o caminho.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function tempPayload(array $items): string
    {
        $path = tempnam(sys_get_temp_dir(), 'redesim');
        file_put_contents($path, json_encode($items, JSON_THROW_ON_ERROR));

        return $path;
    }

    /**
     * Estrutura de um item válido (espelha payload-valido.json).
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validItem(array $overrides = []): array
    {
        $item = [
            'protocolo' => 'BAP2612345678',
            'data_solicitacao' => '2026-06-10T14:30:00-03:00',
            'evento' => ['codigo' => '101', 'descricao' => 'Inscrição de primeiro estabelecimento'],
            'empresa' => [
                'cnpj' => '00000000000191',
                'razao_social' => 'BANCO DO BRASIL SA',
                'nome_fantasia' => 'DIRECAO GERAL',
                'natureza_juridica' => ['codigo' => '2038', 'descricao' => 'Sociedade de Economia Mista'],
                'porte' => ['codigo' => '05', 'descricao' => 'DEMAIS'],
            ],
            'endereco' => [
                'cep' => '40020000',
                'logradouro' => 'Rua Chile',
                'numero' => '10',
                'complemento' => '',
                'bairro' => 'Centro',
                'municipio' => 'Salvador',
                'uf' => 'BA',
            ],
            'atividades' => ['principal' => '6422100', 'secundarias' => ['6499999']],
            'contato' => ['email' => 'banco@example.com', 'telefone' => '7133330000'],
        ];

        return array_replace_recursive($item, $overrides);
    }

    private function service(): RedesimImportService
    {
        return app(RedesimImportService::class);
    }

    private function seedBancoDoBrasilCnaes(): void
    {
        Cnae::factory()->create(['code' => '6422100']);
        Cnae::factory()->create(['code' => '6499999']);
    }

    public function test_payload_valido_cria_empresa_com_cnaes_e_endereco(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $relatorio = $this->service()->import(base_path('tests/Fixtures/redesim/payload-valido.json'));

        $this->assertDatabaseHas('companies', [
            'cnpj' => '00000000000191',
            'legal_name' => 'BANCO DO BRASIL SA',
            'source' => 'redesim',
            'city' => 'Salvador',
            'redesim_protocol' => 'BAP2612345678',
        ]);

        $company = Company::query()->where('cnpj', '00000000000191')->firstOrFail();
        $this->assertSame('6422100', $company->primaryCnae()->first()?->code);
        $this->assertSame(1, $company->cnaes()->wherePivot('is_primary', false)->count());

        $this->assertSame(1, $relatorio['lidos']);
        $this->assertSame(1, $relatorio['importados']);
        $this->assertSame(0, $relatorio['atualizados']);
    }

    public function test_import_e_auditado_com_relatorio_e_versao_de_regras(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $this->service()->import(base_path('tests/Fixtures/redesim/payload-valido.json'));

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'empresas',
            'event' => 'importacao-redesim',
            'rules_version' => 'redesim-import-v1',
            'result' => 'sucesso',
        ]);
    }

    public function test_item_com_cnpj_invalido_e_rejeitado_sem_insercao_parcial(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $path = $this->tempPayload([$this->validItem(['empresa' => ['cnpj' => '11111111111111']])]);
        $relatorio = $this->service()->import($path);

        $this->assertDatabaseCount('companies', 0);
        $this->assertNotEmpty($relatorio['rejeitados']);
        $this->assertStringContainsString('BAP2612345678', $relatorio['rejeitados'][0]);
        $this->assertStringContainsString('CNPJ inválido', $relatorio['rejeitados'][0]);
    }

    public function test_item_sem_razao_social_e_rejeitado(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $path = $this->tempPayload([$this->validItem(['empresa' => ['razao_social' => '']])]);
        $relatorio = $this->service()->import($path);

        $this->assertDatabaseCount('companies', 0);
        $this->assertNotEmpty($relatorio['rejeitados']);
    }

    public function test_item_sem_protocolo_e_rejeitado(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $item = $this->validItem();
        unset($item['protocolo']);
        $path = $this->tempPayload([$item]);
        $relatorio = $this->service()->import($path);

        $this->assertDatabaseCount('companies', 0);
        $this->assertNotEmpty($relatorio['rejeitados']);
    }

    public function test_item_com_cnae_principal_inexistente_e_rejeitado(): void
    {
        // Não seedar nenhum CNAE — o principal 6422100 não existe.
        $path = $this->tempPayload([$this->validItem()]);
        $relatorio = $this->service()->import($path);

        $this->assertDatabaseCount('companies', 0);
        $this->assertNotEmpty($relatorio['rejeitados']);
        $this->assertStringContainsString('6422100', $relatorio['rejeitados'][0]);
    }

    public function test_cnae_inativo_importa_com_aviso(): void
    {
        Cnae::factory()->inactive()->create(['code' => '6422100']);
        Cnae::factory()->create(['code' => '6499999']);

        $path = $this->tempPayload([$this->validItem()]);
        $relatorio = $this->service()->import($path);

        $this->assertDatabaseHas('companies', ['cnpj' => '00000000000191']);
        $this->assertNotEmpty($relatorio['avisos']);
        $this->assertStringContainsString('6422100', implode(' ', $relatorio['avisos']));
    }

    public function test_cnae_secundario_inexistente_gera_aviso_e_e_ignorado(): void
    {
        Cnae::factory()->create(['code' => '6422100']);
        // secundária 8888888 não seedada

        $path = $this->tempPayload([$this->validItem(['atividades' => ['principal' => '6422100', 'secundarias' => ['8888888']]])]);
        $relatorio = $this->service()->import($path);

        $company = Company::query()->where('cnpj', '00000000000191')->firstOrFail();
        $this->assertSame(1, $company->cnaes()->count());
        $this->assertSame('6422100', $company->primaryCnae()->first()?->code);
        $this->assertStringContainsString('8888888', implode(' ', $relatorio['avisos']));
    }

    public function test_reimport_atualiza_sem_duplicar_e_preserva_source(): void
    {
        $this->seedBancoDoBrasilCnaes();

        Company::factory()->create([
            'cnpj' => '00000000000191',
            'source' => 'manual',
            'legal_name' => 'NOME ANTIGO',
        ]);

        $relatorio = $this->service()->import(base_path('tests/Fixtures/redesim/payload-valido.json'));

        $this->assertDatabaseCount('companies', 1);
        $this->assertSame(1, $relatorio['atualizados']);
        $this->assertSame(0, $relatorio['importados']);

        $company = Company::query()->where('cnpj', '00000000000191')->firstOrFail();
        $this->assertSame('manual', $company->source->value);
        $this->assertNotNull($company->redesim_synced_at);
        $this->assertSame('BAP2612345678', $company->redesim_protocol);
        $this->assertSame('BANCO DO BRASIL SA', $company->legal_name);
    }

    public function test_import_nao_cria_vinculo_usuario_empresa(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $this->service()->import(base_path('tests/Fixtures/redesim/payload-valido.json'));

        $this->assertDatabaseCount('company_user', 0);
    }

    public function test_comando_importa_arquivo_e_imprime_relatorio(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $this->artisan('redesim:importar', ['arquivo' => base_path('tests/Fixtures/redesim/payload-valido.json')])
            ->expectsOutputToContain('Importados: 1')
            ->assertExitCode(0);

        $this->assertDatabaseHas('companies', ['cnpj' => '00000000000191']);
    }

    public function test_comando_com_arquivo_inexistente_falha(): void
    {
        $this->artisan('redesim:importar', ['arquivo' => 'storage/nao-existe.json'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertExitCode(1);
    }

    public function test_comando_com_json_invalido_falha(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'redesim');
        file_put_contents($path, 'não-json');

        $this->artisan('redesim:importar', ['arquivo' => $path])
            ->expectsOutputToContain('JSON inválido')
            ->assertExitCode(1);
    }

    public function test_comando_com_todos_os_itens_rejeitados_retorna_falha(): void
    {
        $this->seedBancoDoBrasilCnaes();

        $path = $this->tempPayload([$this->validItem(['empresa' => ['cnpj' => '11111111111111']])]);

        $this->artisan('redesim:importar', ['arquivo' => $path])
            ->expectsOutputToContain('Rejeitados')
            ->assertExitCode(1);
    }

    public function test_nao_existe_rota_publica_de_import(): void
    {
        $this->assertFalse(Route::has('portal.empresas.importar-redesim'));

        $this->post('/portal/empresas/importar-redesim')->assertNotFound();
    }
}
