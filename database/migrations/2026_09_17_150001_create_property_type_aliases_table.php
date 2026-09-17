<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('property_type_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_type_id')->constrained()->cascadeOnDelete();
            $table->string('alias')->unique(); // normalizado; um alias pertence a UM tipo
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_type_aliases');
    }
};
