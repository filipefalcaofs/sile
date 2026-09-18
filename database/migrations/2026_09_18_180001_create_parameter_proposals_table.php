<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parameter_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parameter_id')->constrained('parameters')->restrictOnDelete();
            $table->text('proposed_value');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20);
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['parameter_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parameter_proposals');
    }
};
