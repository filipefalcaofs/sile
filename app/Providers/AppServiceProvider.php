<?php

namespace App\Providers;

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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(function () {
            $config = config('sile.security.password');

            $rule = Password::min($config['min_length']);

            if ($config['require_mixed_case']) {
                $rule->mixedCase();
            }

            if ($config['require_numbers']) {
                $rule->numbers();
            }

            if ($config['require_symbols']) {
                $rule->symbols();
            }

            return $rule;
        });

        config(['auth.passwords.users.expire' => (int) Settings::get('security.password_reset_expire', 60)]);
    }
}
