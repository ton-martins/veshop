<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'slug']);
            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'name']);
            $table->index(['contractor_id', 'parent_id', 'is_active'], 'categories_contractor_parent_active_idx');
        });

        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('sku', 80)->nullable();
            $table->text('description')->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->enum('unit', ['un', 'kg', 'lts'])->default('un');
            $table->string('image_url', 2048)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_pdv_featured')->default(false);
            $table->unsignedTinyInteger('pdv_featured_order')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'sku']);
            $table->index(['contractor_id', 'name']);
            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'stock_quantity']);
            $table->index(['contractor_id', 'category_id']);
            $table->index(['contractor_id', 'is_pdv_featured', 'pdv_featured_order'], 'products_pdv_featured_idx');
        });

        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('image_path', 512);
            $table->string('image_url', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->string('alt_text', 160)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'product_id', 'image_path'], 'product_images_unique_path');
            $table->index(['contractor_id', 'product_id', 'sort_order'], 'product_images_contractor_product_sort_idx');
        });

        Schema::create('product_variations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('sku', 80)->nullable();
            $table->json('attributes')->nullable();
            $table->decimal('cost_price', 10, 2)->nullable();
            $table->decimal('sale_price', 10, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'sku'], 'product_variations_contractor_sku_unique');
            $table->index(['contractor_id', 'product_id', 'is_active', 'sort_order'], 'product_variations_contractor_product_active_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variations');
        Schema::dropIfExists('product_images');
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
    }
};
