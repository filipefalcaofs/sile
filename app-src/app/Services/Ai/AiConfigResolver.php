<?php

namespace App\Services\Ai;

use App\Models\AiConfiguration;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Ponte de runtime (Fase 14, Task 6) entre a configuração de IA ADMINISTRÁVEL
 * (model AiConfiguration, Onda 0) e o SDK laravel/ai em config('ai.*').
 *
 * Lê as configurações ATIVAS do banco e monta os overrides do SDK usando as
 * chaves REAIS do pacote (v0.8.x): driver/key/url/models.text.default (NÃO
 * api_key/base_url) e os defaults por capacidade (ai.default para texto,
 * ai.default_for_embeddings para embeddings). provider 'compativel' mapeia para
 * o driver 'openai' (endpoint OpenAI-compatible).
 *
 * Resiliência (anti-fachada): sem nenhuma configuração ativa, NÃO sobrescreve —
 * o SDK fica com os defaults do vendor e a IA permanece atrás de um toggle OFF.
 *
 * Segurança (RN-009/LGPD): a api_key é cacheada SEMPRE como CIPHERTEXT (nunca em
 * claro), idêntica ao que está no banco; é decriptada APENAS em apply(), para a
 * config em memória do processo. Nunca é persistida em claro nem logada.
 *
 * Cache: o array resolvido é cacheado por um TTL técnico curto (ai.config_cache_ttl)
 * para não ler o banco a cada boot; a gravação/exclusão de uma AiConfiguration
 * invalida o cache na hora (evento do model chama flushCache()).
 */
class AiConfigResolver
{
    public const CACHE_KEY = 'sile.ai.runtime_config';

    /**
     * provider administrável → driver do SDK. 'compativel' = endpoint
     * OpenAI-compatible (mesmo protocolo do driver openai).
     *
     * @var array<string, string>
     */
    private const PROVIDER_DRIVERS = [
        'openai' => 'openai',
        'anthropic' => 'anthropic',
        'gemini' => 'gemini',
        'azure' => 'azure',
        'compativel' => 'openai',
    ];

    /**
     * capacidade → chave de "provider padrão" do SDK. Só as chaves que EXISTEM
     * no laravel/ai. 'vision' é texto multimodal (Files\Image) e não tem default
     * próprio no SDK: a configuração de visão registra o provider em
     * ai.providers.* e é selecionada por chamada (nunca um default forjado).
     *
     * @var array<string, string>
     */
    private const CAPABILITY_DEFAULT_KEYS = [
        'text' => 'ai.default',
        'embeddings' => 'ai.default_for_embeddings',
    ];

    /**
     * Aplica a configuração do banco sobre config('ai.*'). Sem configuração
     * ativa, retorna sem tocar nos defaults do vendor (degradação honesta).
     */
    public function apply(): void
    {
        $resolved = $this->resolve();

        if ($resolved['defaults'] === [] && $resolved['providers'] === []) {
            return;
        }

        foreach ($resolved['defaults'] as $key => $value) {
            config([$key => $value]);
        }

        foreach ($resolved['providers'] as $name => $override) {
            if (array_key_exists('key', $override)) {
                $override['key'] = $override['key'] !== null
                    ? Crypt::decryptString($override['key'])
                    : null;
            }

            config([
                "ai.providers.{$name}" => array_replace_recursive(
                    (array) config("ai.providers.{$name}", []),
                    $override,
                ),
            ]);
        }
    }

    /**
     * Monta (com cache curto) os overrides do SDK a partir das configurações
     * ativas. A api_key vai como ciphertext (decriptada só em apply()).
     *
     * @return array{defaults: array<string, string>, providers: array<string, array<string, mixed>>}
     */
    public function resolve(): array
    {
        $ttl = (int) Settings::get('ai.config_cache_ttl', 60);

        try {
            return Cache::remember(self::CACHE_KEY, $ttl, fn (): array => $this->build());
        } catch (Throwable) {
            // Banco/cache indisponível no boot (migrate, CI, console antes da
            // migração): degrada para vazio — mantém os defaults do vendor.
            return ['defaults' => [], 'providers' => []];
        }
    }

    /**
     * Invalida o cache da ponte. Chamado pelos eventos saved/deleted do model
     * AiConfiguration — efeito sem deploy (CA HU-014).
     */
    public static function flushCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{defaults: array<string, string>, providers: array<string, array<string, mixed>>}
     */
    private function build(): array
    {
        $defaults = [];
        $providers = [];

        $configurations = AiConfiguration::query()
            ->where('active', true)
            ->orderBy('id')
            ->get();

        foreach ($configurations as $configuration) {
            $provider = $configuration->provider;
            $driver = self::PROVIDER_DRIVERS[$provider] ?? $provider;
            $modelSlot = $configuration->capability === 'embeddings' ? 'embeddings' : 'text';

            $override = $providers[$provider] ?? [];
            $override['driver'] = $driver;

            // Ciphertext do banco (nunca o valor em claro): cacheável com segurança.
            $rawKey = $configuration->getRawOriginal('api_key');
            $override['key'] = $rawKey !== null && $rawKey !== '' ? $rawKey : null;

            if ($configuration->base_url !== null && $configuration->base_url !== '') {
                $override['url'] = $configuration->base_url;
            }

            if ($configuration->model !== null && $configuration->model !== '') {
                $override['models'][$modelSlot]['default'] = $configuration->model;
            }

            $providers[$provider] = $override;

            $defaultKey = self::CAPABILITY_DEFAULT_KEYS[$configuration->capability] ?? null;

            if ($configuration->is_default && $defaultKey !== null) {
                $defaults[$defaultKey] = $provider;
            }
        }

        return ['defaults' => $defaults, 'providers' => $providers];
    }
}
