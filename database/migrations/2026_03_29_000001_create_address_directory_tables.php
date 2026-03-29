<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('address_states', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 2)->unique();
            $table->string('name', 120);
            $table->string('ibge_code', 12)->nullable()->unique();
            $table->timestamp('cities_synced_at')->nullable();
            $table->timestamps();

            $table->index('name');
        });

        Schema::create('address_cities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('address_state_id')->constrained('address_states')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('normalized_name', 120);
            $table->string('ibge_code', 12)->nullable()->unique();
            $table->timestamps();

            $table->unique(['address_state_id', 'normalized_name'], 'address_cities_state_normalized_unique');
            $table->index(['address_state_id', 'name'], 'address_cities_state_name_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address_cities');
        Schema::dropIfExists('address_states');
    }
};
