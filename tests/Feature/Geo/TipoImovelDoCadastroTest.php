<?php

namespace Tests\Feature\Geo;

use App\Models\User;
use App\Models\ViabilityRequest;
use App\Services\Geo\TerritorioProcessoService;
use App\Services\Realty\PropertyRegistryLookup;
use App\Services\Realty\PropertyRegistryResult;
use App\Services\Realty\PropertyRegistryUnavailableException;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tipo do imóvel pela Utilizacao do Cadastro Imobiliário: gravado no processo em
 * campo próprio (o tipo do REGIN decide o encaminhamento e não pode ser trocado),
 * mostrado nas listas quando o REGIN não trouxe e preenchido nos processos antigos
 * por comando.
 */
class TipoImovelDoCadastroTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @param  array<string, string|null>  $utilizacaoPorInscricao  null simula o cadastro fora do ar
     */
    private function fakeCadastro(array $utilizacaoPorInscricao): void
    {
        $this->app->instance(PropertyRegistryLookup::class, new class($utilizacaoPorInscricao) implements PropertyRegistryLookup
        {
            public int $chamadas = 0;

            /**
             * @param  array<string, string|null>  $utilizacaoPorInscricao
             */
            public function __construct(private array $utilizacaoPorInscricao) {}

            public function resolve(string $inscricao): PropertyRegistryResult
            {
                $this->chamadas++;

                if (! array_key_exists($inscricao, $this->utilizacaoPorInscricao) || $this->utilizacaoPorInscricao[$inscricao] === null) {
                    throw new PropertyRegistryUnavailableException($inscricao);
                }

                return new PropertyRegistryResult(
                    latitude: -12.99,
                    longitude: -38.52,
                    inscricao: $inscricao,
                    raw: ['CodigoLogradouroTributario' => '212', 'Utilizacao' => $this->utilizacaoPorInscricao[$inscricao]],
                );
            }
        });
    }

    private function processo(?string $inscricao, array $atributos = []): ViabilityRequest
    {
        return ViabilityRequest::factory()->create(['property_registration' => $inscricao] + $atributos);
    }

    public function test_grava_a_utilizacao_do_cadastro_sem_tocar_no_tipo_do_regin(): void
    {
        $this->fakeCadastro(['350940' => 'NÃO RESIDENCIAL VERTICAL']);
        $processo = $this->processo('350940');
        $processo->forceFill(['tipo_imovel' => 'Sala', 'tipo_imovel_normalized' => 'sala'])->save();

        $this->assertTrue(app(TerritorioProcessoService::class)->gravarTipoImovelDoCadastro($processo));

        $processo->refresh();
        $this->assertSame('NÃO RESIDENCIAL VERTICAL', $processo->tipo_imovel_cadastro);
        $this->assertSame('Sala', $processo->tipo_imovel);
        $this->assertSame('sala', $processo->tipo_imovel_normalized);
    }

    public function test_sem_inscricao_ou_com_cadastro_fora_do_ar_nao_grava_nada(): void
    {
        $this->fakeCadastro(['111' => null]);

        $this->assertFalse(app(TerritorioProcessoService::class)->gravarTipoImovelDoCadastro($this->processo(null)));
        $this->assertFalse(app(TerritorioProcessoService::class)->gravarTipoImovelDoCadastro($semCadastro = $this->processo('111')));
        $this->assertNull($semCadastro->fresh()->tipo_imovel_cadastro);
    }

    public function test_cadastro_sem_utilizacao_nao_apaga_o_valor_gravado(): void
    {
        $this->fakeCadastro(['222' => '']);
        $processo = $this->processo('222', ['tipo_imovel_cadastro' => 'COMERCIAL']);

        $this->assertFalse(app(TerritorioProcessoService::class)->gravarTipoImovelDoCadastro($processo));
        $this->assertSame('COMERCIAL', $processo->fresh()->tipo_imovel_cadastro);
    }

    public function test_comando_simula_sem_gravar_e_aplica_so_com_a_opcao(): void
    {
        $this->fakeCadastro(['350940' => 'RESIDENCIAL HORIZONTAL', '999' => 'COMERCIAL']);
        $faltando = $this->processo('350940');
        $jaTem = $this->processo('999', ['tipo_imovel_cadastro' => 'COMERCIAL']);
        $semInscricao = $this->processo(null);

        $this->artisan('processos:preencher-tipo-imovel', ['--pausa-ms' => 0])->assertSuccessful();
        $this->assertNull($faltando->fresh()->tipo_imovel_cadastro, 'sem --aplicar nada é gravado');

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true, '--pausa-ms' => 0])->assertSuccessful();

        $this->assertSame('RESIDENCIAL HORIZONTAL', $faltando->fresh()->tipo_imovel_cadastro);
        $this->assertSame('COMERCIAL', $jaTem->fresh()->tipo_imovel_cadastro);
        $this->assertNull($semInscricao->fresh()->tipo_imovel_cadastro);
    }

    public function test_comando_respeita_o_limite_e_um_processo_so(): void
    {
        $this->fakeCadastro(['1' => 'COMERCIAL', '2' => 'COMERCIAL', '3' => 'COMERCIAL']);
        $a = $this->processo('1');
        $b = $this->processo('2');
        $c = $this->processo('3');

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true, '--limite' => 1, '--pausa-ms' => 0])->assertSuccessful();
        $this->assertSame(1, ViabilityRequest::query()->whereNotNull('tipo_imovel_cadastro')->count());

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true, '--processo' => $c->id, '--pausa-ms' => 0])->assertSuccessful();
        $this->assertNotNull($c->fresh()->tipo_imovel_cadastro);
    }

    public function test_comando_continua_quando_o_cadastro_cai_no_meio(): void
    {
        $this->fakeCadastro(['1' => null, '2' => 'COMERCIAL']);
        $this->processo('1');
        $b = $this->processo('2');

        $this->artisan('processos:preencher-tipo-imovel', ['--aplicar' => true, '--pausa-ms' => 0])->assertSuccessful();

        $this->assertSame('COMERCIAL', $b->fresh()->tipo_imovel_cadastro);
    }

    public function test_listas_mostram_o_do_regin_e_na_falta_dele_o_do_cadastro(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->administrador()->withAcceptedLgpdTerm()->create();
        $semRegin = $this->processo(null, ['tipo_imovel_cadastro' => 'NÃO RESIDENCIAL VERTICAL']);
        $comRegin = $this->processo(null, ['tipo_imovel_cadastro' => 'COMERCIAL']);
        $comRegin->forceFill(['tipo_imovel' => 'Sala'])->save();

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/processos/{$semRegin->id}")
            ->assertInertia(fn (Assert $page) => $page->where('processo.tipo_imovel', 'NÃO RESIDENCIAL VERTICAL'));

        $this->actingAs($admin, 'gestao')
            ->get("/gestao/processos/{$comRegin->id}")
            ->assertInertia(fn (Assert $page) => $page->where('processo.tipo_imovel', 'Sala'));
    }

    public function test_capa_da_vistoria_recebe_o_tipo_do_cadastro(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $vistoriador = User::factory()->analista()->withAcceptedLgpdTerm()->create();
        $processo = $this->processo(null, ['assigned_user_id' => $vistoriador->id, 'tipo_imovel_cadastro' => 'RESIDENCIAL VERTICAL']);

        $this->actingAs($vistoriador, 'gestao')
            ->get("/gestao/processos/{$processo->id}/vistoria")
            ->assertInertia(fn (Assert $page) => $page->where('processo.tipo_imovel_cadastro', 'RESIDENCIAL VERTICAL'));
    }
}
