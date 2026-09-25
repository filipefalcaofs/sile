<?php

use App\Services\Sector\OfficialSectorCatalogSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homologação/produção: código CEIN estável nos setores, histórico de lotação
 * (user_sector_assignments) e substituição do catálogo pela planilha oficial.
 * A carga de dados não roda na suíte (padrão CatalogoOficialSeeder) — cada
 * teste semeia o que precisa; SectorSeeder chama o sync em local/testing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sectors', function (Blueprint $table) {
            $table->string('code', 20)->nullable()->unique()->after('id');
        });

        Schema::create('user_sector_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('previous_sector_id')->nullable()->constrained('sectors')->nullOnDelete();
            $table->string('source', 32);
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'ended_at']);
            $table->index(['sector_id', 'ended_at']);
        });

        if (app()->runningUnitTests() || app()->environment('testing')) {
            return;
        }

        app(OfficialSectorCatalogSync::class)->sync();
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sector_assignments');

        Schema::table('sectors', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
