<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const array CHEFIAS = ['Chefe de setor', 'Coordenador', 'Subcoordenador'];

    /**
     * Zona e via escolhidas à mão na ficha de análise. Ficam em colunas próprias
     * porque a execução do expresso regrava a zona calculada: a escolha manual
     * vale sobre ela, e a justificativa e o histórico vão no log de alteração.
     */
    public function up(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->string('zona_manual', 40)->nullable();
            $table->string('via_manual', 16)->nullable();
        });

        Permission::firstOrCreate(['name' => 'editar-zona-via', 'guard_name' => 'web']);

        Role::query()->whereIn('name', self::CHEFIAS)->where('guard_name', 'web')->get()
            ->each(fn (Role $perfil) => $perfil->givePermissionTo('editar-zona-via'));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Schema::table('viability_requests', function (Blueprint $table): void {
            $table->dropColumn(['zona_manual', 'via_manual']);
        });
    }
};
