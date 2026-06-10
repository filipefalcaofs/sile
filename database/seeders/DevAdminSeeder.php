<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DevAdminSeeder extends Seeder
{
    /**
     * Usuário administrador de DESENVOLVIMENTO (admin@sile.dev / password).
     * Nunca executar em produção com estas credenciais.
     *
     * O CPF fixo 111.444.777-35 é válido pelo algoritmo e distinto dos CPFs
     * de exemplo do roteiro de smoke, para não colidir com cadastros manuais.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@sile.dev'],
            [
                'name' => 'Administrador SILE',
                'cpf' => '11144477735',
                'phone' => null,
                'password' => 'password',
            ],
        );

        if ($admin->email_verified_at === null) {
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->assignRole('administrador');
    }
}
