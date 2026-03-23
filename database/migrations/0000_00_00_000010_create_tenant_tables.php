<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
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

        Schema::create('contractors', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('cnpj')->unique()->nullable();
            $table->string('slug')->unique();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->date('contract_starts_at')->nullable();
            $table->date('contract_ends_at')->nullable();
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->json('address')->nullable();
            $table->string('brand_name')->nullable();
            $table->string('brand_primary_color')->nullable();
            $table->string('brand_logo_url')->nullable();
            $table->string('brand_avatar_url')->nullable();
            $table->json('settings')->nullable();
            $table->string('business_type', 60)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contractor_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained('contractors')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['contractor_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contractor_user');
        Schema::dropIfExists('contractors');
        Schema::dropIfExists('plans');
    }
};
