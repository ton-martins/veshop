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
        Schema::create('service_appointments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
            $table->string('title', 180);
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 30)->default('scheduled');
            $table->string('location', 180)->nullable();
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'starts_at', 'ends_at']);
            $table->index(['contractor_id', 'status', 'starts_at']);
            $table->index(['contractor_id', 'client_id', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_appointments');
    }
};
