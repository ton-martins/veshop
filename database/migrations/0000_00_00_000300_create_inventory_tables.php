<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->unsignedBigInteger('sale_item_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->integer('quantity');
            $table->integer('balance_before')->nullable();
            $table->integer('balance_after')->nullable();
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->string('reason', 255)->nullable();
            $table->string('reference_type', 120)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'product_id', 'occurred_at']);
            $table->index(['contractor_id', 'type', 'occurred_at']);
            $table->index(['contractor_id', 'sale_item_id']);
            $table->index(['contractor_id', 'reference_type', 'reference_id'], 'inv_mov_contractor_reference_idx');
            $table->index(['contractor_id', 'product_variation_id', 'occurred_at'], 'inv_mov_contractor_variation_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
