<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Recusa de regra de negócio (422 fora de validação) numa visita Inertia volta
 * à página anterior com aviso, em vez da página genérica "Oops".
 */
class InertiaErroNegocioTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('web')->group(function (): void {
            Route::post('/_teste/abort-422', fn () => abort(422, 'Regra de negócio recusou.'));
            Route::get('/_teste/abort-422', fn () => abort(422, 'Regra de negócio recusou.'));
            Route::post('/_teste/abort-403', fn () => abort(403));
            Route::post('/_teste/validacao', fn (Request $request) => $request->validate(['campo' => 'required']));
        });
    }

    public function test_422_em_visita_inertia_volta_com_flash_de_erro(): void
    {
        $this->from('/origem')
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/_teste/abort-422')
            ->assertRedirect('/origem')
            ->assertSessionHas('error', 'Regra de negócio recusou.');
    }

    public function test_422_em_requisicao_json_continua_422(): void
    {
        $this->postJson('/_teste/abort-422')->assertStatus(422);
    }

    public function test_validacao_em_visita_inertia_continua_com_errors(): void
    {
        $this->from('/origem')
            ->withHeaders(['X-Inertia' => 'true'])
            ->post('/_teste/validacao')
            ->assertSessionHasErrors('campo')
            ->assertSessionMissing('error');
    }

    public function test_403_em_visita_inertia_nao_muda(): void
    {
        $this->withHeaders(['X-Inertia' => 'true'])
            ->post('/_teste/abort-403')
            ->assertForbidden();
    }

    public function test_422_em_get_inertia_nao_redireciona_para_nao_entrar_em_laco(): void
    {
        $this->from('/_teste/abort-422')
            ->withHeaders([
                'X-Inertia' => 'true',
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
            ])
            ->get('/_teste/abort-422')
            ->assertStatus(422);
    }
}
