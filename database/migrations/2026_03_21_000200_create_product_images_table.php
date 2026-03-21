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
        // Em caso de falha parcial anterior (tabela criada sem índices), recria a estrutura.
        if (Schema::hasTable('product_images')) {
            Schema::drop('product_images');
        }

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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
