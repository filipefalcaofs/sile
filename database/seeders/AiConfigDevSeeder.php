<?php

namespace Database\Seeders;

use App\Models\AiConfiguration;
use Illuminate\Database\Seeder;

/**
 * Configuração de IA de exemplo para DESENVOLVIMENTO (Fase 14, Onda 0): um
 * provedor openai/texto padrão e ativo, para a tela de configuração e a ponte
 * de runtime ficarem navegáveis sobre a LÓGICA REAL (config('ai.*') passa a
 * refletir o banco).
 *
 * Gate de ambiente (anti-fachada): SÓ em `local` (não em `testing`, de
 * propósito) — a suíte semeia por caso e o DatabaseSeederTest assere contagens
 * exatas; somar este exemplo as quebraria. Em produção é no-op (o administrador
 * cadastra os provedores reais pela tela).
 *
 * NUNCA usa credencial real: a api_key é um placeholder explícito ('sk-DEV-…')
 * e nunca vem do .env versionado. Idempotente (updateOrCreate por nome) e não
 * sobrescreve uma chave real que o dev tenha cadastrado pela interface.
 */
class AiConfigDevSeeder extends Seeder
{
    public const NAME = 'OpenAI (exemplo de desenvolvimento)';

    private const PLACEHOLDER_API_KEY = 'sk-DEV-placeholder-nao-real';

    public function run(): void
    {
        // GATE DE AMBIENTE (anti-fachada): exemplo de IA só em local.
        if (! app()->environment('local')) {
            $this->command?->warn('AiConfigDevSeeder: ignorado fora de local (exemplo de configuração de IA de dev).');

            return;
        }

        $configuration = AiConfiguration::updateOrCreate(
            ['name' => self::NAME],
            [
                'provider' => 'openai',
                'capability' => 'text',
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
                'temperature' => 0.10,
                'max_tokens' => 4096,
                'timeout_ms' => 60000,
                'active' => true,
            ],
        );

        // Credencial placeholder só na criação: não sobrescreve uma chave real
        // que o dev tenha gravado pela tela (RN-009 — chave em branco mantém).
        if ($configuration->getRawOriginal('api_key') === null) {
            $configuration->update(['api_key' => self::PLACEHOLDER_API_KEY]);
        }

        // Garante a invariante "uma configuração padrão por capacidade".
        if (! $configuration->is_default) {
            $configuration->setAsDefault();
        }
    }
}
