<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_pendency_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_pendency_id')->constrained('analysis_pendencies')->cascadeOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_pendency_attachments');
    }
};
