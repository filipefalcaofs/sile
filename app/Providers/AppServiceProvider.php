<?php

namespace App\Providers;

use App\Listeners\AuditModelsPruned;
use App\Listeners\LogNotificationSent;
use App\Services\Cnpj\BrasilApiCnpjLookup;
use App\Services\Cnpj\CnpjLookup;
use App\Services\GovBr\GovBrIdTokenValidator;
use App\Services\GovBr\GovBrProvider;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Database\Events\ModelsPruned;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentRepresentation::class);

        // Provider público inicial (BrasilAPI / dados abertos RFB). A Fase 13
        // (HU-105) troca este binding pelo provider conveniado da Receita
        // Federal sem tocar controllers ou telas.
        $this->app->bind(CnpjLookup::class, BrasilApiCnpjLookup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Event::listen(NotificationSent::class, LogNotificationSent::class);

        // Auditoria da retenção (RN-002 / SC#1): a poda em massa de access_logs
        // não dispara model events, mas emite ModelsPruned — registrado aqui.
        Event::listen(ModelsPruned::class, AuditModelsPruned::class);

        Password::defaults(function () {
            $rule = Password::min((int) Settings::get('security.password.min_length', 8));

            if (Settings::get('security.password.require_mixed_case', true)) {
                $rule->mixedCase();
            }

            if (Settings::get('security.password.require_numbers', true)) {
                $rule->numbers();
            }

            if (Settings::get('security.password.require_symbols', false)) {
                $rule->symbols();
            }

            return $rule;
        });

        config(['auth.passwords.users.expire' => (int) Settings::get('security.password_reset_expire', 60)]);

        // Driver Socialite do Login Único (HU-151). Credenciais e URL são
        // lidas dos parâmetros administráveis A CADA resolução do driver —
        // trocar staging/produção ou rotacionar credencial não exige deploy.
        Socialite::extend('govbr', function ($app): GovBrProvider {
            $provider = new GovBrProvider(
                $app['request'],
                (string) Settings::get('integrations.govbr.client_id', ''),
                (string) Settings::get('integrations.govbr.client_secret', ''),
                route('portal.govbr.callback'),
            );

            return $provider
                ->withBaseUrl((string) Settings::get('integrations.govbr.base_url'))
                ->withIdTokenValidator($app->make(GovBrIdTokenValidator::class));
        });
    }
}
