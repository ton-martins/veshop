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
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_catalog_id')->nullable()->constrained('service_catalogs')->nullOnDelete();
            $table->string('code', 80);
            $table->string('title', 180);
            $table->string('description', 500)->nullable();
            $table->dateTime('scheduled_for')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->string('status', 30)->default('open');
            $table->string('priority', 20)->default('normal');
            $table->string('assigned_to_name', 120)->nullable();
            $table->decimal('estimated_amount', 12, 2)->default(0);
            $table->decimal('final_amount', 12, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'code']);
            $table->index(['contractor_id', 'status', 'due_at']);
            $table->index(['contractor_id', 'scheduled_for']);
            $table->index(['contractor_id', 'client_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_orders');
    }
};
