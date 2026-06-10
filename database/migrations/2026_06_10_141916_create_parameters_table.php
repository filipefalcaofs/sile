<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('parameters', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('group')->index();
            $table->string('type', 20);
            $table->text('value')->nullable();
            $table->text('default_value')->nullable();
            $table->json('validation_rules');
            $table->boolean('sensitive')->default(false);
            $table->string('description');
            $table->boolean('requires_connection_test')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parameters');
    }
};
