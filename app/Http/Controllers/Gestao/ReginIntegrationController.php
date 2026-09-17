<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateReginIntegrationRequest;
use App\Models\Parameter;
use App\Services\Regin\ReginConnectionTester;
use App\Services\Regin\ReginIntegrationSettings;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ReginIntegrationController extends Controller
{
    public function __construct(
        private ReginIntegrationSettings $settings,
        private AuditService $audit,
    ) {}

    public function edit(): Response
    {
        $senha = Parameter::query()->where('key', 'integrations.regin.senha')->first();

        return Inertia::render('gestao/config-regin/index', [
            'config' => [
                'em_producao' => $this->settings->usingProduction(),
                'url_homologacao' => (string) Settings::get(
                    'integrations.regin.url_homologacao',
                    config('sile.integrations.regin.url_homologacao'),
                ),
                'url_producao' => (string) Settings::get(
                    'integrations.regin.url_producao',
                    config('sile.integrations.regin.url_producao'),
                ),
                'usuario' => $this->settings->username(),
                'senha' => null,
                'url_ativa' => $this->settings->baseUrl(),
                'tem_senha' => $senha?->getRawOriginal('value') !== null,
            ],
        ]);
    }

    public function update(UpdateReginIntegrationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->persist('integrations.regin.em_producao', $data['em_producao'] ? '1' : '0');
        $this->persist('integrations.regin.url_homologacao', $data['url_homologacao']);
        $this->persist('integrations.regin.url_producao', $data['url_producao']);
        $this->persist('integrations.regin.usuario', $data['usuario']);
        $this->persist('integrations.regin.senha', $data['senha'] ?? '');

        return back()->with('status', 'Configuração REGIN atualizada.');
    }

    public function test(ReginConnectionTester $tester): RedirectResponse
    {
        $result = $tester->test();

        $this->audit->log(
            'config-regin',
            'testar',
            'Teste de conexão da API REGIN',
            [
                'ambiente' => $this->settings->usingProduction() ? 'producao' : 'homologacao',
                'url' => $this->settings->baseUrl(),
            ],
            result: $result->ok ? 'sucesso' : 'falha',
        );

        if (! $result->ok) {
            return back()->withErrors(['regin_connection' => $result->mensagem]);
        }

        return back()->with('status', $result->mensagem);
    }

    private function persist(string $key, string $value): void
    {
        $parameter = Parameter::query()->where('key', $key)->firstOrFail();

        if ($parameter->sensitive && $value === '') {
            return;
        }

        $old = $parameter->value;
        $parameter->update(['value' => $value]);

        $this->audit->log(
            'parametros',
            'parametro-alterado',
            "Parâmetro {$parameter->key} alterado",
            [
                'key' => $parameter->key,
                'valor_anterior' => $parameter->sensitive ? '[criptografado]' : $old,
                'valor_novo' => $parameter->sensitive ? '[criptografado]' : $value,
            ],
            $parameter,
        );
    }
}
