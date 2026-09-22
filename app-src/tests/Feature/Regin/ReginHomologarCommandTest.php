<?php

namespace Tests\Feature\Regin;

use App\Models\Activity;
use App\Models\Parameter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReginHomologarCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Parameter::factory()->create([
            'key' => 'integrations.regin.em_producao',
            'group' => 'integracoes',
            'type' => 'boolean',
            'value' => '0',
            'default_value' => '0',
        ]);
        Parameter::factory()->create([
            'key' => 'integrations.regin.url_homologacao',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'http://10.57.247.9:8080/api_integracao',
            'default_value' => 'http://10.57.247.9:8080/api_integracao',
        ]);
        Parameter::factory()->create([
            'key' => 'integrations.regin.usuario',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'sedur_integracao',
            'default_value' => 'sedur_integracao',
        ]);
        Parameter::factory()->sensitive()->create([
            'key' => 'integrations.regin.senha',
            'group' => 'integracoes',
            'type' => 'string',
            'value' => 'segredo-forte',
            'default_value' => null,
        ]);
    }

    public function test_comando_valida_e_testa_recebimento_e_audita_sucesso(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-hml']),
            '*/teste/validaResposta' => Http::response('OK'),
            '*/teste/testeRecebimento' => Http::response('OK'),
        ]);

        $this->artisan('regin:homologar', ['--protocolo' => '43747'])
            ->assertSuccessful();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/teste/validaResposta')
            && $request->hasHeader('JWT', 'jwt-hml'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/teste/testeRecebimento')
            && $request->hasHeader('JWT', 'jwt-hml'));

        $this->assertSame(2, Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-homologacao')
            ->where('result', 'sucesso')
            ->count());
    }

    public function test_comando_audita_falha_sem_vazar_token(): void
    {
        Http::fake([
            '*/acesso/auth' => Http::response(['token' => 'jwt-hml']),
            '*/teste/validaResposta' => Http::response('erro', 500),
        ]);

        $this->artisan('regin:homologar', ['--protocolo' => '43747'])
            ->assertFailed();

        $atividade = Activity::query()
            ->where('log_name', 'integracoes')
            ->where('event', 'regin-homologacao')
            ->where('result', 'bloqueado')
            ->first();

        $this->assertNotNull($atividade);
        $this->assertStringNotContainsString('jwt-hml', json_encode($atividade->properties, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('segredo-forte', $atividade->description);
    }
}
