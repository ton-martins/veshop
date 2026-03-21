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
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreignId('product_variation_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variations')
                ->nullOnDelete();

            $table->index(
                ['contractor_id', 'product_variation_id'],
                'sale_items_contractor_product_variation_idx'
            );
        });

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreignId('product_variation_id')
                ->nullable()
                ->after('product_id')
                ->constrained('product_variations')
                ->nullOnDelete();

            $table->index(
                ['contractor_id', 'product_variation_id', 'occurred_at'],
                'inv_mov_contractor_variation_occurred_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropIndex('inv_mov_contractor_variation_occurred_idx');
            $table->dropConstrainedForeignId('product_variation_id');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropIndex('sale_items_contractor_product_variation_idx');
            $table->dropConstrainedForeignId('product_variation_id');
        });
    }
};

