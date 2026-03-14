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
        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('cnpj')->unique()->nullable();
            $table->string('slug')->unique();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->json('address')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('brand_primary_color')->nullable();
            $table->string('brand_logo_url')->nullable();
            $table->string('brand_avatar_url')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contractors');
    }
};
