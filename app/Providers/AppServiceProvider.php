<?php

namespace App\Providers;

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
