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
        Schema::create('payment_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_gateway_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('event_key', 120);
            $table->string('transaction_reference', 160)->nullable();
            $table->string('sale_code', 80)->nullable();
            $table->string('status', 40)->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['contractor_id', 'payment_gateway_id', 'event_key'],
                'payment_webhook_receipts_unique_event'
            );
            $table->index(['contractor_id', 'provider', 'created_at']);
            $table->index(['contractor_id', 'transaction_reference']);
            $table->index(['contractor_id', 'sale_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_receipts');
    }
};
