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
        Schema::create('accounting_fee_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_label', 20);
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'status', 'due_date'], 'acc_fee_status_due_idx');
            $table->index(['contractor_id', 'client_id', 'due_date'], 'acc_fee_client_due_idx');
        });

        Schema::create('accounting_obligations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('obligation_type', 80)->nullable();
            $table->date('competence_date')->nullable();
            $table->date('due_date');
            $table->string('status', 30)->default('pending');
            $table->string('priority', 20)->default('normal');
            $table->timestamp('completed_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'status', 'due_date'], 'acc_obg_status_due_idx');
            $table->index(['contractor_id', 'priority', 'due_date'], 'acc_obg_priority_due_idx');
            $table->index(['contractor_id', 'client_id', 'due_date'], 'acc_obg_client_due_idx');
        });

        Schema::create('accounting_document_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title', 180);
            $table->string('document_type', 80)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->timestamp('received_at')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'status', 'due_date'], 'acc_doc_status_due_idx');
            $table->index(['contractor_id', 'client_id', 'due_date'], 'acc_doc_client_due_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounting_document_requests');
        Schema::dropIfExists('accounting_obligations');
        Schema::dropIfExists('accounting_fee_entries');
    }
};
