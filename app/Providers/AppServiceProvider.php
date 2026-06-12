<?php

namespace App\Providers;

use App\Services\Cnpj\BrasilApiCnpjLookup;
use App\Services\Cnpj\CnpjLookup;
use App\Support\Representation\CurrentRepresentation;
use App\Support\Settings;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
    }
}
