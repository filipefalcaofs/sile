<?php

namespace App\Providers;

use App\Services\Ai\AiConfigResolver;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Ponte de runtime da configuração de IA administrável (model AiConfiguration)
 * com o SDK laravel/ai. No boot, aplica os provedores ATIVOS do banco sobre
 * config('ai.*') via AiConfigResolver — exatamente o que torna a tela de
 * configuração uma feature real (não decorativa).
 *
 * Degradação segura (anti-fachada, espelha o MailConfigServiceProvider): sem
 * tabela migrada (boot durante migrate/CI) ou sem nenhuma configuração ativa,
 * NADA é alterado e o SDK mantém os defaults do vendor — a IA fica atrás de um
 * toggle features.ia_* OFF até o administrador cadastrar e ativar um provedor.
 */
class AiConfigServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        try {
            if (! Schema::hasTable('ai_configurations')) {
                return;
            }

            $this->app->make(AiConfigResolver::class)->apply();
        } catch (Throwable) {
            // Banco/cache indisponível no boot (ex.: migrate antes da tabela) ou
            // credencial indecifrável (APP_KEY trocada): mantém os defaults do
            // vendor. Nunca quebra o boot; nunca simula uma config aplicada.
        }
    }
}
