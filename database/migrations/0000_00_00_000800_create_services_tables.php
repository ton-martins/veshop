<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'slug']);
            $table->index(['contractor_id', 'name']);
            $table->index(['contractor_id', 'is_active']);
        });

        Schema::create('service_catalogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('code', 80)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'code']);
            $table->index(['contractor_id', 'name']);
            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'service_category_id']);
        });

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

    public function down(): void
    {
        Schema::dropIfExists('service_appointments');
        Schema::dropIfExists('service_orders');
        Schema::dropIfExists('service_catalogs');
        Schema::dropIfExists('service_categories');
    }
};
