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
            $table->index(
                ['contractor_id', 'product_id', 'is_active', 'sort_order'],
                'product_variations_contractor_product_active_sort_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variations');
    }
};

