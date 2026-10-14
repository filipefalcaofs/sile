<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array DESTINO = [
        'analista' => 'Analista Terceirizado',
        'gestor' => 'Coordenador',
    ];

    public function up(): void
    {
        $antigos = Role::query()->whereIn('name', array_keys(self::DESTINO))->get();

        // Banco novo (testes, ambiente limpo): nada a migrar.
        if ($antigos->isEmpty()) {
            return;
        }

        // Cria os perfis de destino e concede as permissões da análise antes de mover as contas.
        app(RolesAndPermissionsSeeder::class)->run();

        DB::transaction(function () use ($antigos): void {
            foreach ($antigos as $antigo) {
                $destino = Role::findByName(self::DESTINO[$antigo->name], 'web');

                $vinculos = DB::table('model_has_roles')->where('role_id', $antigo->id)->get();

                foreach ($vinculos as $vinculo) {
                    $jaTemDestino = DB::table('model_has_roles')
                        ->where('role_id', $destino->id)
                        ->where('model_type', $vinculo->model_type)
                        ->where('model_id', $vinculo->model_id)
                        ->exists();

                    if (! $jaTemDestino) {
                        DB::table('model_has_roles')->insert([
                            'role_id' => $destino->id,
                            'model_type' => $vinculo->model_type,
                            'model_id' => $vinculo->model_id,
                        ]);
                    }
                }

                DB::table('model_has_roles')->where('role_id', $antigo->id)->delete();
                $antigo->delete();
            }

            $administrador = Role::query()->where('name', 'administrador')->where('guard_name', 'web')->first();
            $analisar = Permission::query()->where('name', 'analisar-processos')->where('guard_name', 'web')->first();

            if ($administrador !== null && $analisar !== null) {
                $administrador->revokePermissionTo($analisar);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Irreversível: as contas já foram reatribuídas aos perfis de análise da SEDUR.
    }
};
