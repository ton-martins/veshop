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
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_entries');
    }
};
