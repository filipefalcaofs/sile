<?php

namespace Tests\Feature\Portal;

use App\Models\Cidadao;
use App\Models\Parameter;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Os parâmetros do portal ficam numa aba própria e cada serviço tem o seu
 * interruptor (renovação, consulta de processo e convite), além do geral.
 */
class PortalParametrosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const CHAVES_DO_PORTAL = [
        'features.portal_servicos',
        'features.portal_renovacao',
        'features.portal_consulta_processo',
        'features.portal_convites',
        'features.login_portal_servicos',
        'features.consulta_viabilidade',
        'features.govbr_login',
        'renovacao.intervalo_meses',
        'renovacao.legado.janela_a_vencer_dias',
        'renovacao.legado.permite_vencida',
        'integrations.portal_servicos.bff_base_url',
        'integrations.portal_servicos.login_url',
        'integrations.portal_servicos.carta_url',
        'integrations.portal_servicos.intervalo_checagem_sessao',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function cidadao(): Cidadao
    {
        return Cidadao::factory()->withAcceptedLgpdTerm()->create();
    }

    private function chave(string $chave, bool $ligada): void
    {
        config(["viabiliza.features.{$chave}" => $ligada]);
        Parameter::query()->where('key', "features.{$chave}")->update(['value' => $ligada ? '1' : '0']);
        Cache::forget("viabiliza.parameters.features.{$chave}");
    }

    public function test_seeder_agrupa_os_parametros_do_portal_na_aba_portal(): void
    {
        $this->seed(ParameterSeeder::class);

        foreach (self::CHAVES_DO_PORTAL as $chave) {
            $this->assertSame(
                'portal',
                Parameter::query()->where('key', $chave)->value('group'),
                "{$chave} deveria estar na aba portal",
            );
        }
    }

    public function test_interruptores_por_servico_nascem_ligados_e_o_geral_desligado(): void
    {
        $this->seed(ParameterSeeder::class);

        foreach (['portal_renovacao', 'portal_consulta_processo', 'portal_convites'] as $servico) {
            $this->assertTrue(Parameter::query()->where('key', "features.{$servico}")->first()->typedValue());
        }

        $this->assertFalse(Parameter::query()->where('key', 'features.portal_servicos')->first()->typedValue());
    }

    public function test_a_descricao_do_interruptor_geral_cita_os_tres_servicos(): void
    {
        $this->seed(ParameterSeeder::class);

        $descricao = Parameter::query()->where('key', 'features.portal_servicos')->value('description');

        $this->assertStringContainsString('renovação', $descricao);
        $this->assertStringContainsString('consulta de processo', $descricao);
        $this->assertStringContainsString('convite', $descricao);
    }

    public function test_migration_cria_as_chaves_e_move_o_grupo_sem_tocar_nos_valores(): void
    {
        $this->seed(ParameterSeeder::class);

        Parameter::query()->whereIn('key', [
            'features.portal_renovacao',
            'features.portal_consulta_processo',
            'features.portal_convites',
        ])->delete();
        Parameter::query()->whereIn('key', self::CHAVES_DO_PORTAL)->update(['group' => 'features']);
        Parameter::query()->where('key', 'features.portal_servicos')->update(['value' => '1']);

        $migration = require database_path('migrations/2026_10_27_120000_cria_aba_portal_dos_parametros.php');
        $migration->up();
        $migration->up();

        foreach (self::CHAVES_DO_PORTAL as $chave) {
            $this->assertSame('portal', Parameter::query()->where('key', $chave)->value('group'), $chave);
        }

        $this->assertSame('1', Parameter::query()->where('key', 'features.portal_servicos')->value('value'));
        $this->assertSame(1, Parameter::query()->where('key', 'features.portal_renovacao')->count());
    }

    public function test_renovacao_desligada_nao_afeta_consulta_nem_convite(): void
    {
        $this->chave('portal_renovacao', false);
        $user = $this->cidadao();

        $this->actingAs($user)->get('/portal/renovacao')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning');
        $this->actingAs($user)->postJson('/portal/renovacao/legado/consultar', [])->assertStatus(503);

        $this->actingAs($user)->get('/portal/consulta')->assertOk();
        $this->actingAs($user)->get('/portal/convites')->assertOk();
    }

    public function test_consulta_de_processo_desligada_nao_afeta_renovacao_nem_convite(): void
    {
        $this->chave('portal_consulta_processo', false);
        $user = $this->cidadao();

        $this->actingAs($user)->get('/portal/consulta')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($user)->get('/portal/renovacao')->assertOk();
        $this->actingAs($user)->get('/portal/convites')->assertOk();
    }

    public function test_convite_desligado_nao_afeta_renovacao_nem_consulta(): void
    {
        $this->chave('portal_convites', false);
        $user = $this->cidadao();

        $this->actingAs($user)->get('/portal/convites')
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHas('warning');

        $this->actingAs($user)->get('/portal/renovacao')->assertOk();
        $this->actingAs($user)->get('/portal/consulta')->assertOk();
    }

    public function test_interruptor_geral_continua_derrubando_os_tres_servicos(): void
    {
        config(['viabiliza.features.portal_servicos' => false]);
        Cache::forget('viabiliza.parameters.features.portal_servicos');
        $user = $this->cidadao();

        foreach (['/portal/renovacao', '/portal/consulta', '/portal/convites'] as $url) {
            $this->actingAs($user)->get($url)->assertRedirect()->assertSessionHas('warning');
        }
    }
}
