<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_gateways', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 60);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_sandbox')->default(true);
            $table->json('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_health_check_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'provider']);
            $table->index(['contractor_id', 'is_default']);
        });

        Schema::create('payment_methods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained('payment_gateways')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 120);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->boolean('allows_installments')->default(false);
            $table->unsignedTinyInteger('max_installments')->nullable();
            $table->decimal('fee_fixed', 10, 2)->nullable();
            $table->decimal('fee_percent', 5, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'code']);
            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'is_default']);
            $table->index(['contractor_id', 'payment_gateway_id']);
        });

        Schema::create('sales', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('cash_session_id')->nullable();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('shop_customer_id')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 80);
            $table->string('checkout_idempotency_key', 80)->nullable();
            $table->string('source', 30)->default('pdv');
            $table->string('status', 30)->default('draft');
            $table->decimal('subtotal_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('surcharge_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->decimal('change_amount', 12, 2)->default(0);
            $table->string('shipping_mode', 20)->nullable();
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->unsignedSmallInteger('shipping_estimate_days')->nullable();
            $table->json('shipping_address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'code']);
            $table->unique(['contractor_id', 'checkout_idempotency_key'], 'sales_contractor_checkout_idempotency_unique');
            $table->index(['contractor_id', 'status', 'completed_at']);
            $table->index(['contractor_id', 'cash_session_id', 'status']);
            $table->index(['contractor_id', 'source', 'created_at']);
            $table->index(['contractor_id', 'shipping_mode']);
            $table->index(['contractor_id', 'shop_customer_id']);
        });

        Schema::create('sale_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            $table->string('description');
            $table->string('sku', 80)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'sale_id']);
            $table->index(['contractor_id', 'product_id']);
            $table->index(['contractor_id', 'product_variation_id'], 'sale_items_contractor_product_variation_idx');
        });

        Schema::create('sale_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_gateway_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->decimal('amount', 12, 2);
            $table->unsignedTinyInteger('installments')->nullable();
            $table->string('transaction_reference', 160)->nullable();
            $table->json('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'sale_id']);
            $table->index(['contractor_id', 'status', 'paid_at']);
            $table->index(['contractor_id', 'payment_method_id']);
            $table->index(['contractor_id', 'payment_gateway_id']);
            $table->index(['contractor_id', 'transaction_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_gateways');
    }
};
