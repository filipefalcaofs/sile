<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('driver')->default('smtp'); // smtp (extensível: ses, resend...)
            $table->string('host');
            $table->unsignedInteger('port')->default(587);
            $table->string('encryption')->default('tls'); // tls | ssl | none
            $table->unsignedInteger('timeout')->default(30); // segundos
            $table->string('username')->nullable();
            $table->text('password')->nullable();  // criptografada (RN-009) — nunca em claro
            $table->string('from_address');
            $table->string('from_name');
            $table->boolean('active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('is_default');
            $table->index('active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_servers');
    }
};
