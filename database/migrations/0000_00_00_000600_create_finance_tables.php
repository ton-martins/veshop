<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('type', ['payable', 'receivable']);
            $table->enum('status', ['pending', 'paid', 'cancelled'])->default('pending');
            $table->string('counterparty_name', 160);
            $table->string('reference', 160)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('issue_date')->nullable();
            $table->date('due_date');
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'type', 'status'], 'fin_entries_scope_status_idx');
            $table->index(['contractor_id', 'due_date'], 'fin_entries_due_idx');
            $table->index(['contractor_id', 'created_at'], 'fin_entries_created_idx');
        });

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

            $table->unique(['contractor_id', 'payment_gateway_id', 'event_key'], 'payment_webhook_receipts_unique_event');
            $table->index(['contractor_id', 'provider', 'created_at'], 'pwr_contractor_provider_created_idx');
            $table->index(['contractor_id', 'transaction_reference'], 'pwr_contractor_tx_ref_idx');
            $table->index(['contractor_id', 'sale_code'], 'pwr_contractor_sale_code_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_receipts');
        Schema::dropIfExists('financial_entries');
    }
};
