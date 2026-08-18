<?php

namespace App\Http\Controllers\Gestao;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gestao\StoreAiConfigurationRequest;
use App\Http\Requests\Gestao\UpdateAiConfigurationRequest;
use App\Models\AiConfiguration;
use App\Services\Ai\AiProviderClient;
use App\Support\Audit\AuditService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manutenção dos provedores de IA administráveis (Fase 14, Onda 0 — HU-014
 * aplicada à IA). Irmão da configuração de e-mail: CRUD server-driven sob a
 * permissão manter-config-ia. A api_key é criptografada no model e NUNCA
 * reexibida — a listagem usa masked_api_key; na edição, chave em branco mantém
 * a atual. O model JAMAIS é serializado direto para o Inertia (o cast decripta
 * a chave no toArray): a listagem monta um DTO campo a campo. A auditoria
 * (RN-002) é explícita e NUNCA inclui a credencial nem o corpo do provedor. O
 * teste de conexão faz chamada HTTP REAL (anti-fachada).
 */
class AiConfigurationController extends Controller
{
    public function __construct(private AuditService $audit) {}

    public function index(): Response
    {
        $configurations = AiConfiguration::query()
            ->orderByDesc('is_default')
            ->orderBy('capability')
            ->orderBy('name')
            ->get()
            ->map(fn (AiConfiguration $config): array => $this->present($config));

        return Inertia::render('gestao/config-ia/index', [
            'configurations' => $configurations,
        ]);
    }

    public function store(StoreAiConfigurationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        $config = AiConfiguration::create($data);

        if ($isDefault) {
            $config->setAsDefault();
        }

        $this->audit->log('config-ia', 'criar', "Configuração de IA '{$config->name}' cadastrada", $this->auditProps($config), $config);

        return back()->with('status', 'Configuração de IA cadastrada com sucesso.');
    }

    public function update(UpdateAiConfigurationRequest $request, AiConfiguration $aiConfiguration): RedirectResponse
    {
        $data = $request->validated();
        $isDefault = (bool) ($data['is_default'] ?? false);
        unset($data['is_default']);

        // Chave em branco = manter a atual (RN-009: nunca apaga a credencial por omissão).
        if (($data['api_key'] ?? '') === '') {
            unset($data['api_key']);
        }

        $aiConfiguration->update($data);

        if ($isDefault) {
            $aiConfiguration->setAsDefault();
        }

        $this->audit->log('config-ia', 'atualizar', "Configuração de IA '{$aiConfiguration->name}' atualizada", $this->auditProps($aiConfiguration), $aiConfiguration);

        return back()->with('status', 'Configuração de IA atualizada com sucesso.');
    }

    public function destroy(AiConfiguration $aiConfiguration): RedirectResponse
    {
        $nome = $aiConfiguration->name;
        $props = $this->auditProps($aiConfiguration);

        $aiConfiguration->delete();

        $this->audit->log('config-ia', 'excluir', "Configuração de IA '{$nome}' excluída", $props);

        return back()->with('status', 'Configuração de IA excluída.');
    }

    public function test(AiConfiguration $aiConfiguration, AiProviderClient $client): RedirectResponse
    {
        $result = $client->testConnection($aiConfiguration);

        $this->audit->log(
            'config-ia',
            'testar',
            "Teste de conexão da configuração de IA '{$aiConfiguration->name}'",
            $this->auditProps($aiConfiguration),
            $aiConfiguration,
            result: $result->ok ? 'sucesso' : 'falha',
        );

        if (! $result->ok) {
            return back()->withErrors(['ai_connection' => 'Falha na conexão: '.$result->mensagem]);
        }

        return back()->with('status', $result->mensagem);
    }

    public function toggleActivation(AiConfiguration $aiConfiguration): RedirectResponse
    {
        $aiConfiguration->update(['active' => ! $aiConfiguration->active]);

        $this->audit->log(
            'config-ia',
            $aiConfiguration->active ? 'ativar' : 'desativar',
            "Configuração de IA '{$aiConfiguration->name}' ".($aiConfiguration->active ? 'ativada' : 'desativada'),
            $this->auditProps($aiConfiguration),
            $aiConfiguration,
        );

        return back()->with('status', 'Situação da configuração de IA atualizada.');
    }

    /**
     * DTO de exibição — expõe a credencial SÓ mascarada, NUNCA em claro.
     *
     * @return array<string, mixed>
     */
    private function present(AiConfiguration $config): array
    {
        return [
            'id' => $config->id,
            'name' => $config->name,
            'provider' => $config->provider,
            'capability' => $config->capability,
            'base_url' => $config->base_url,
            'model' => $config->model,
            'temperature' => $config->temperature,
            'max_tokens' => $config->max_tokens,
            'timeout_ms' => $config->timeout_ms,
            'masked_api_key' => $config->masked_api_key,
            'active' => $config->active,
            'is_default' => $config->is_default,
        ];
    }

    /**
     * Propriedades auditáveis — NUNCA incluem a api_key nem o corpo do provedor
     * (RN-002 sem segredo). personal_data=false: configuração técnica, sem PII.
     *
     * @return array<string, mixed>
     */
    private function auditProps(AiConfiguration $config): array
    {
        return [
            'ai_configuration_id' => $config->id,
            'name' => $config->name,
            'provider' => $config->provider,
            'capability' => $config->capability,
            'model' => $config->model,
            'base_url' => $config->base_url,
            'is_default' => $config->is_default,
            'active' => $config->active,
        ];
    }
}
