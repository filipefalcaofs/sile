<?php

namespace Tests\Feature\Gestao;

use App\Enums\AnalysisStatus;
use App\Enums\ViabilityRequestStatus;
use App\Models\AnalysisRecord;
use App\Models\Company;
use App\Models\User;
use App\Models\ViabilityRequest;
use App\Models\ViabilityRequestEvento;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Alteração de endereço da JUCEB: a ficha mostra o serviço identificado e o que
 * mudou em relação ao último processo da empresa, campo a campo.
 */
class FichaMudancaEnderecoTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const ENDERECO = 'Alteração de endereço dentro do mesmo município';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function analista(): User
    {
        $usuario = User::factory()->withAcceptedLgpdTerm()->create();
        $usuario->givePermissionTo(['acessar-gestao', 'analisar-processos']);

        return $usuario;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function processo(Company $empresa, array $atributos = [], ?string $evento = self::ENDERECO, ?string $condicao = null): ViabilityRequest
    {
        $processo = ViabilityRequest::factory()->protocoled()->create([
            'company_id' => $empresa->id,
            'protocol_number' => 'VIA-2026-'.fake()->unique()->numerify('######'),
            'cod_log' => '1234',
            'address_street' => 'Rua A',
            'address_number' => '10',
            'address_neighborhood' => 'Centro',
            'address_complement' => 'Sala 1',
            'address_zip' => '40000000',
            'used_area_m2' => 50,
            ...$atributos,
        ]);
        $processo->forceFill(['status' => ViabilityRequestStatus::EmAnalise, 'analysis_status' => AnalysisStatus::EmAnalise])->save();
        AnalysisRecord::factory()->create(['viability_request_id' => $processo->id, 'revision' => 1]);

        if ($evento !== null) {
            ViabilityRequestEvento::factory()->create(['viability_request_id' => $processo->id, 'codigo' => '300', 'nome' => $evento, 'condicao' => $condicao]);
        }

        return $processo;
    }

    public function test_revisao_de_espaco_mostra_area_e_complemento_antes_e_depois(): void
    {
        $empresa = Company::factory()->create();
        $anterior = $this->processo($empresa, [], null);
        $atual = $this->processo($empresa, ['used_area_m2' => 80, 'address_complement' => 'Sala 2'], self::ENDERECO, 'endereco_espaco');

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$atual->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('mudancaEndereco.servico', 'Revisão de Espaço')
                ->where('mudancaEndereco.processo_anterior', $anterior->protocol_number)
                ->has('mudancaEndereco.campos', 2)
                ->where('mudancaEndereco.campos.0', ['campo' => 'Complemento', 'antes' => 'Sala 1', 'depois' => 'Sala 2'])
                ->where('mudancaEndereco.campos.1', ['campo' => 'Área utilizada (m²)', 'antes' => '50,00', 'depois' => '80,00']));
    }

    public function test_alteracao_de_endereco_mostra_numero_e_bairro(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, [], null);
        $atual = $this->processo($empresa, ['address_number' => '20', 'address_neighborhood' => 'Barra'], self::ENDERECO, 'endereco_localizacao');

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$atual->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('mudancaEndereco.servico', 'Alteração de Endereço')
                ->where('mudancaEndereco.campos.0', ['campo' => 'Nº', 'antes' => '10', 'depois' => '20'])
                ->where('mudancaEndereco.campos.1', ['campo' => 'Bairro', 'antes' => 'Centro', 'depois' => 'Barra']));
    }

    public function test_sem_processo_anterior_avisa_que_nao_ha_base(): void
    {
        $atual = $this->processo(Company::factory()->create());

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$atual->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page
                ->where('mudancaEndereco.processo_anterior', null)
                ->where('mudancaEndereco.campos', []));
    }

    public function test_pedido_sem_alteracao_de_endereco_nao_traz_o_bloco(): void
    {
        $empresa = Company::factory()->create();
        $this->processo($empresa, [], null);
        $atual = $this->processo($empresa, ['used_area_m2' => 80], 'Alteração de atividades econômicas (principal e secundárias)');

        $this->actingAs($this->analista(), 'gestao')
            ->get("/gestao/processos/{$atual->id}/ficha")
            ->assertInertia(fn (Assert $page) => $page->where('mudancaEndereco', null));
    }
}
