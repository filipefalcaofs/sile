<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\UpdateInscricaoImobiliariaIntegrationRequest;
use App\Models\Parameter;
use App\Services\Realty\InscricaoImobiliariaConnectionTester;
use App\Services\Realty\InscricaoImobiliariaIntegrationSettings;
use App\Support\Audit\AuditService;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class InscricaoImobiliariaIntegrationController extends Controller
{
    public function __construct(
        private InscricaoImobiliariaIntegrationSettings $settings,
        private AuditService $audit,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('gestao/config-inscricao-imobiliaria/index', [
            'config' => [
                'em_producao' => $this->settings->usingProduction(),
                'url_homologacao' => (string) Settings::get(
                    'integrations.inscricao_imobiliaria.url_homologacao',
                    config('sile.integrations.inscricao_imobiliaria.url_homologacao'),
                ),
                'url_producao' => (string) Settings::get(
                    'integrations.inscricao_imobiliaria.url_producao',
                    config('sile.integrations.inscricao_imobiliaria.url_producao'),
                ),
                'url_ativa' => $this->settings->baseUrl(),
            ],
        ]);
    }

    public function update(UpdateInscricaoImobiliariaIntegrationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $this->persist('integrations.inscricao_imobiliaria.em_producao', $data['em_producao'] ? '1' : '0');
        $this->persist('integrations.inscricao_imobiliaria.url_homologacao', $data['url_homologacao']);
        $this->persist('integrations.inscricao_imobiliaria.url_producao', $data['url_producao']);
        $this->persist(
            'integrations.inscricao_imobiliaria.base_url',
            $data['em_producao'] ? $data['url_producao'] : $data['url_homologacao'],
        );

        return back()->with('status', 'Configuração da API de inscrição imobiliária atualizada.');
    }

    public function test(InscricaoImobiliariaConnectionTester $tester): RedirectResponse
    {
        $result = $tester->test();

        $this->audit->log(
            'config-inscricao-imobiliaria',
            'testar',
            'Teste de conexão da API de inscrição imobiliária',
            [
                'ambiente' => $this->settings->usingProduction() ? 'producao' : 'homologacao',
                'url' => $this->settings->baseUrl(),
            ],
            result: $result->ok ? 'sucesso' : 'falha',
        );

        if (! $result->ok) {
            return back()->withErrors(['inscricao_connection' => $result->mensagem]);
        }

        return back()->with('status', $result->mensagem);
    }

    private function persist(string $key, string $value): void
    {
        $parameter = Parameter::query()->where('key', $key)->firstOrFail();

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
