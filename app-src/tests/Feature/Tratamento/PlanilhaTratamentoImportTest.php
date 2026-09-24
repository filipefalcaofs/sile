<?php

namespace Tests\Feature\Tratamento;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\TratamentoCnluItem;
use App\Models\TratamentoCondicionante;
use App\Models\TratamentoEnquadramento;
use App\Models\TratamentoPergunta;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PlanilhaTratamentoImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $planilha;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->planilha = base_path('docs/artefatos/Planilha de regras - versão 20.08.26.xlsx');
    }

    public function test_importa_a_planilha_como_rascunho_e_recusa_o_mesmo_arquivo(): void
    {
        $autor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/enquadramento/importar', ['arquivo' => $this->arquivo()])
            ->assertRedirect('/gestao/louos?aba=enquadramento');

        $versao = RuleVersion::query()->where('domain', RuleDomain::RiscoTratamento)->first();

        $this->assertNotNull($versao);
        $this->assertSame(RuleVersionStatus::Rascunho, $versao->status);
        $this->assertNotNull($versao->content_hash);
        $this->assertSame(32, TratamentoPergunta::query()->where('rule_version_id', $versao->id)->count());
        $this->assertSame(1332, TratamentoEnquadramento::query()->where('rule_version_id', $versao->id)->distinct()->count('cnae'));
        $this->assertSame(32, TratamentoCondicionante::query()->where('rule_version_id', $versao->id)->count());
        $this->assertTrue(TratamentoCnluItem::query()->where('rule_version_id', $versao->id)->where('codigo', 'nRa')->exists());

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/enquadramento/importar', ['arquivo' => $this->arquivo()])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, RuleVersion::query()->where('domain', RuleDomain::RiscoTratamento)->count());
    }

    public function test_o_mesmo_usuario_publica_o_rascunho(): void
    {
        $autor = User::factory()->administrador()->withAcceptedLgpdTerm()->create();

        $this->actingAs($autor, 'gestao')
            ->post('/gestao/louos/enquadramento/importar', ['arquivo' => $this->arquivo()])
            ->assertRedirect();

        $versao = RuleVersion::query()->where('domain', RuleDomain::RiscoTratamento)->firstOrFail();

        $this->actingAs($autor, 'gestao')
            ->put("/gestao/louos/enquadramento/versoes/{$versao->id}/publicar")
            ->assertRedirect('/gestao/louos?aba=enquadramento');

        $this->assertSame(RuleVersionStatus::Vigente, $versao->refresh()->status);
        $this->assertSame($autor->id, $versao->published_by);
    }

    private function arquivo(): UploadedFile
    {
        return new UploadedFile(
            $this->planilha,
            'Planilha de regras - versão 20.08.26.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );
    }
}
