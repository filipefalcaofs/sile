<?php

namespace Tests\Feature\Gestao;

use App\Models\Parameter;
use App\Models\User;
use Database\Seeders\ParameterSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TreinoIaGaleriaControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ParameterSeeder::class);
        Storage::fake('local');
    }

    public function test_parametro_nasce_desligado(): void
    {
        $this->assertSame('0', Parameter::query()->where('key', 'features.treino_ia_galeria')->value('value') ?? Parameter::query()->where('key', 'features.treino_ia_galeria')->value('default_value'));
    }

    public function test_parametro_desligado_devolve_404(): void
    {
        $this->galeria('20261022-teste');

        $this->actingAs($this->admin(), 'gestao')->get('/gestao/treino-ia')->assertNotFound();
        $this->actingAs($this->admin(), 'gestao')->get('/gestao/treino-ia/20261022-teste')->assertNotFound();
    }

    public function test_sem_manter_parametros_devolve_403(): void
    {
        $this->ligar();

        $this->actingAs(User::factory()->analista()->withAcceptedLgpdTerm()->create(), 'gestao')
            ->get('/gestao/treino-ia')
            ->assertForbidden();
    }

    public function test_lista_execucoes_a_partir_dos_resumos(): void
    {
        $this->ligar();
        $this->galeria('20261022-teste');
        $this->galeria('20261022-cego');

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/treino-ia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/treino-ia/index')
                ->has('execucoes', 2)
                ->where('execucoes.0.execucao', '20261022-teste')
                ->where('execucoes.0.lotes', 60)
                ->where('execucoes.0.mediana_iou_por_variante.E', 0.71));
    }

    public function test_sem_galerias_mostra_estado_vazio(): void
    {
        $this->ligar();

        $this->actingAs($this->admin(), 'gestao')
            ->get('/gestao/treino-ia')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('execucoes', 0));
    }

    public function test_serve_o_index_e_as_imagens_da_execucao(): void
    {
        $this->ligar();
        $this->galeria('20261022-teste');

        $indice = $this->actingAs($this->admin(), 'gestao')->get('/gestao/treino-ia/20261022-teste')->assertOk();
        $this->assertStringContainsString('Galeria de teste', $indice->baseResponse->getFile()->getContent());
        $this->assertStringStartsWith('text/html', $indice->headers->get('Content-Type'));
        $this->actingAs($this->admin(), 'gestao')->get('/gestao/treino-ia/20261022-teste/img/9-1.png')
            ->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_caminho_com_ponto_ponto_absoluto_ou_execucao_inexistente_devolve_404(): void
    {
        $this->ligar();
        $this->galeria('20261022-teste');
        Storage::disk('local')->put('treino-ia/segredo.json', '{"senha":"x"}');

        foreach ([
            '/gestao/treino-ia/20261022-teste/../../segredo.json',
            '/gestao/treino-ia/20261022-teste/%2e%2e/%2e%2e/segredo.json',
            '/gestao/treino-ia/..%2Fsegredo.json',
            '/gestao/treino-ia/20261022-teste//etc/passwd',
            '/gestao/treino-ia/inexistente',
            '/gestao/treino-ia/20261022-teste/resumo.php',
        ] as $url) {
            $resposta = $this->actingAs($this->admin(), 'gestao')->get($url);
            $this->assertSame(404, $resposta->getStatusCode(), $url);
        }
    }

    private function ligar(): void
    {
        Parameter::query()->where('key', 'features.treino_ia_galeria')->update(['value' => '1']);
    }

    private function galeria(string $execucao): void
    {
        $disco = Storage::disk('local');
        $disco->put("treino-ia/galerias/{$execucao}/index.html", '<html>Galeria de teste</html>');
        $disco->put("treino-ia/galerias/{$execucao}/img/9-1.png", base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        $disco->put("treino-ia/galerias/{$execucao}/resumo.json", json_encode([
            'execucao' => $execucao, 'lotes' => 60, 'ordem_variantes' => ['A', 'E'],
            'variantes' => ['A' => ['iou' => ['mediana' => 0.012]], 'E' => ['iou' => ['mediana' => 0.71]]],
        ]));
    }

    private function admin(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }
}
