<?php

namespace Tests\Feature\Processo;

use App\Http\Resources\ProcessoResource;
use App\Models\Company;
use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class ProcessoResourceSolicitanteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_empresa_a_constituir_mostra_a_razao_social_sem_cnpj_e_o_solicitante_com_cpf(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '00449085040', 'legal_name' => 'REBOREDO CONSULTORIA LTDA']);
        $processo = ViabilityRequest::factory()->create([
            'company_id' => $empresa->id,
            'solicitante_nome' => 'GRAZIELLE DE FREITAS MUZYKANT',
            'solicitante_cpf' => '00449085040',
        ]);

        $dados = (new ProcessoResource($processo->load('company')))->resolve(new Request);

        $this->assertSame('REBOREDO CONSULTORIA LTDA', $dados['empresa']);
        $this->assertNull($dados['cnpj']);
        $this->assertSame('GRAZIELLE DE FREITAS MUZYKANT', $dados['solicitante']);
        $this->assertSame('004.490.850-40', $dados['solicitante_cpf']);
    }

    public function test_empresa_com_cnpj_mostra_o_cnpj_formatado_e_nao_tem_solicitante(): void
    {
        $empresa = Company::factory()->create(['cnpj' => '13927801000149']);
        $processo = ViabilityRequest::factory()->create(['company_id' => $empresa->id]);

        $dados = (new ProcessoResource($processo->load('company')))->resolve(new Request);

        $this->assertSame('13.927.801/0001-49', $dados['cnpj']);
        $this->assertNull($dados['solicitante']);
        $this->assertNull($dados['solicitante_cpf']);
    }
}
