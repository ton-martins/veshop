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
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('niche', 32);
            $table->string('name');
            $table->string('slug');
            $table->string('badge', 80)->nullable();
            $table->string('subtitle', 180)->nullable();
            $table->text('summary')->nullable();
            $table->text('footer_message')->nullable();
            $table->decimal('price_monthly', 10, 2)->nullable();
            $table->unsignedInteger('max_admin_users')->nullable();
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedInteger('storage_limit_gb')->nullable();
            $table->unsignedInteger('audit_log_retention_days')->nullable();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('show_on_landing')->default(false);
            $table->unsignedInteger('tier_rank')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['niche', 'name']);
            $table->unique(['niche', 'slug']);
            $table->index('niche');
            $table->index(['is_active', 'tier_rank', 'sort_order']);
            $table->index(['is_featured', 'is_active']);
            $table->index(['niche', 'show_on_landing', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
