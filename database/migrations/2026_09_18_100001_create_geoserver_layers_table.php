<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('geoserver_layers', function (Blueprint $table) {
            $table->id();
            $table->string('workspace', 100);
            $table->string('type_name', 150);
            $table->string('label')->nullable();
            $table->boolean('ativo')->default(true);
            $table->unsignedInteger('ordem')->default(0);
            $table->timestamps();

            $table->unique(['workspace', 'type_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('geoserver_layers');
    }
};
