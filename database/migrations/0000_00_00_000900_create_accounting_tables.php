<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_service_templates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->string('code', 80);
            $table->string('name', 160);
            $table->string('category', 80)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('default_obligation_type', 80)->nullable();
            $table->string('default_document_type', 80)->nullable();
            $table->string('default_stage_code', 40)->default('backlog');
            $table->json('checklist_items')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'code'], 'acc_template_contractor_code_unique');
            $table->index(['contractor_id', 'is_active', 'sort_order'], 'acc_template_contractor_active_idx');
        });

        Schema::create('accounting_fee_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference_label', 20);
            $table->date('due_date');
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status', 30)->default('pending');
            $table->string('recurrence_frequency', 20)->default('none');
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->date('next_reference_date')->nullable();
            $table->string('adjustment_type', 20)->default('none');
            $table->decimal('adjustment_value', 10, 4)->default(0);
            $table->boolean('reminder_email_enabled')->default(true);
            $table->boolean('reminder_whatsapp_enabled')->default(false);
            $table->timestamp('overdue_notified_at')->nullable();
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
            $table->string('stage_code', 40)->default('backlog');
            $table->string('assigned_to_name', 120)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('reminder_at')->nullable();
            $table->timestamp('last_reminder_at')->nullable();
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
            $table->foreignId('template_id')->nullable()->constrained('accounting_service_templates')->nullOnDelete();
            $table->string('title', 180);
            $table->string('document_type', 80)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('pending');
            $table->string('protocol_code', 60)->nullable();
            $table->json('checklist_items')->nullable();
            $table->unsignedSmallInteger('pending_items_count')->default(0);
            $table->unsignedInteger('last_version_number')->default(0);
            $table->timestamp('last_reminder_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->string('file_path', 255)->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'status', 'due_date'], 'acc_doc_status_due_idx');
            $table->index(['contractor_id', 'client_id', 'due_date'], 'acc_doc_client_due_idx');
        });

        Schema::create('accounting_client_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('service_regime', 80)->nullable();
            $table->string('contract_number', 80)->nullable();
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->decimal('monthly_fee', 12, 2)->nullable();
            $table->unsignedTinyInteger('billing_day')->nullable();
            $table->unsignedSmallInteger('sla_hours')->nullable();
            $table->string('responsible_name', 160)->nullable();
            $table->string('responsible_email')->nullable();
            $table->string('responsible_phone', 32)->nullable();
            $table->boolean('reminder_email_enabled')->default(true);
            $table->boolean('reminder_whatsapp_enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'client_id'], 'acc_client_profile_unique');
            $table->index(['contractor_id', 'contract_end_date'], 'acc_client_profile_contract_end_idx');
        });

        Schema::create('accounting_task_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_obligation_id')->constrained('accounting_obligations')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('previous_stage', 40)->nullable();
            $table->string('current_stage', 40)->nullable();
            $table->string('previous_status', 30)->nullable();
            $table->string('current_status', 30)->nullable();
            $table->string('assigned_to_name', 120)->nullable();
            $table->date('due_date')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'accounting_obligation_id'], 'acc_task_hist_obligation_idx');
            $table->index(['contractor_id', 'created_at'], 'acc_task_hist_created_idx');
        });

        Schema::create('accounting_document_versions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('contractor_id');
            $table->unsignedBigInteger('accounting_document_request_id');
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('file_path', 255)->nullable();
            $table->string('file_name', 180)->nullable();
            $table->timestamp('uploaded_at')->nullable();
            $table->string('notes', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['accounting_document_request_id', 'version_number'], 'acc_document_version_unique');
            $table->index(['contractor_id', 'uploaded_at'], 'acc_document_version_uploaded_idx');
            $table->foreign('contractor_id', 'acc_doc_ver_contractor_fk')
                ->references('id')
                ->on('contractors')
                ->cascadeOnDelete();
            $table->foreign('accounting_document_request_id', 'acc_doc_ver_doc_req_fk')
                ->references('id')
                ->on('accounting_document_requests')
                ->cascadeOnDelete();
            $table->foreign('created_by_user_id', 'acc_doc_ver_created_by_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        Schema::create('accounting_reminder_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 30);
            $table->string('target', 180)->nullable();
            $table->string('context_type', 40);
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('status', 30)->default('sent');
            $table->string('message', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'context_type', 'context_id'], 'acc_reminder_context_idx');
            $table->index(['contractor_id', 'channel', 'sent_at'], 'acc_reminder_channel_sent_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_reminder_logs');
        Schema::dropIfExists('accounting_document_versions');
        Schema::dropIfExists('accounting_task_histories');
        Schema::dropIfExists('accounting_client_profiles');
        Schema::dropIfExists('accounting_document_requests');
        Schema::dropIfExists('accounting_obligations');
        Schema::dropIfExists('accounting_fee_entries');
        Schema::dropIfExists('accounting_service_templates');
    }
};
