<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('clients') && Schema::hasTable('contractors') && ! Schema::hasTable('accounting_client_profiles')) {
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
        }

        if (Schema::hasTable('contractors') && ! Schema::hasTable('accounting_service_templates')) {
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
        }

        if (Schema::hasTable('accounting_obligations') && Schema::hasTable('contractors') && ! Schema::hasTable('accounting_task_histories')) {
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
        }

        if (Schema::hasTable('accounting_document_requests') && Schema::hasTable('contractors') && ! Schema::hasTable('accounting_document_versions')) {
            Schema::create('accounting_document_versions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('contractor_id')
                    ->constrained(table: 'contractors', indexName: 'acc_doc_ver_contractor_fk')
                    ->cascadeOnDelete();
                $table->foreignId('accounting_document_request_id')
                    ->constrained(table: 'accounting_document_requests', indexName: 'acc_doc_ver_doc_req_fk')
                    ->cascadeOnDelete();
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained(table: 'users', indexName: 'acc_doc_ver_created_by_fk')
                    ->nullOnDelete();
                $table->unsignedInteger('version_number')->default(1);
                $table->string('file_path', 255)->nullable();
                $table->string('file_name', 180)->nullable();
                $table->timestamp('uploaded_at')->nullable();
                $table->string('notes', 500)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['accounting_document_request_id', 'version_number'], 'acc_document_version_unique');
                $table->index(['contractor_id', 'uploaded_at'], 'acc_document_version_uploaded_idx');
            });
        }

        if (Schema::hasTable('accounting_document_versions')) {
            $this->ensureForeignKey(
                tableName: 'accounting_document_versions',
                columnName: 'contractor_id',
                referencedTable: 'contractors',
                referencedColumn: 'id',
                constraintName: 'acc_doc_ver_contractor_fk',
                onDelete: 'cascade'
            );

            $this->ensureForeignKey(
                tableName: 'accounting_document_versions',
                columnName: 'accounting_document_request_id',
                referencedTable: 'accounting_document_requests',
                referencedColumn: 'id',
                constraintName: 'acc_doc_ver_doc_req_fk',
                onDelete: 'cascade'
            );

            $this->ensureForeignKey(
                tableName: 'accounting_document_versions',
                columnName: 'created_by_user_id',
                referencedTable: 'users',
                referencedColumn: 'id',
                constraintName: 'acc_doc_ver_created_by_fk',
                onDelete: 'set null'
            );
        }

        if (Schema::hasTable('contractors') && ! Schema::hasTable('accounting_reminder_logs')) {
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

        if (Schema::hasTable('accounting_fee_entries')) {
            Schema::table('accounting_fee_entries', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_fee_entries', 'recurrence_frequency')) {
                    $table->string('recurrence_frequency', 20)->default('none')->after('status');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'recurrence_interval')) {
                    $table->unsignedSmallInteger('recurrence_interval')->default(1)->after('recurrence_frequency');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'next_reference_date')) {
                    $table->date('next_reference_date')->nullable()->after('recurrence_interval');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'adjustment_type')) {
                    $table->string('adjustment_type', 20)->default('none')->after('next_reference_date');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'adjustment_value')) {
                    $table->decimal('adjustment_value', 10, 4)->default(0)->after('adjustment_type');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'reminder_email_enabled')) {
                    $table->boolean('reminder_email_enabled')->default(true)->after('adjustment_value');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'reminder_whatsapp_enabled')) {
                    $table->boolean('reminder_whatsapp_enabled')->default(false)->after('reminder_email_enabled');
                }
                if (! Schema::hasColumn('accounting_fee_entries', 'overdue_notified_at')) {
                    $table->timestamp('overdue_notified_at')->nullable()->after('reminder_whatsapp_enabled');
                }
            });
        }

        if (Schema::hasTable('accounting_obligations')) {
            Schema::table('accounting_obligations', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_obligations', 'stage_code')) {
                    $table->string('stage_code', 40)->default('backlog')->after('priority');
                }
                if (! Schema::hasColumn('accounting_obligations', 'assigned_to_name')) {
                    $table->string('assigned_to_name', 120)->nullable()->after('stage_code');
                }
                if (! Schema::hasColumn('accounting_obligations', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('assigned_to_name');
                }
                if (! Schema::hasColumn('accounting_obligations', 'reminder_at')) {
                    $table->timestamp('reminder_at')->nullable()->after('started_at');
                }
                if (! Schema::hasColumn('accounting_obligations', 'last_reminder_at')) {
                    $table->timestamp('last_reminder_at')->nullable()->after('reminder_at');
                }
            });
        }

        if (Schema::hasTable('accounting_document_requests')) {
            Schema::table('accounting_document_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('accounting_document_requests', 'template_id') && Schema::hasTable('accounting_service_templates')) {
                    $table->foreignId('template_id')->nullable()->after('client_id')->constrained('accounting_service_templates')->nullOnDelete();
                }
                if (! Schema::hasColumn('accounting_document_requests', 'protocol_code')) {
                    $table->string('protocol_code', 60)->nullable()->after('status');
                }
                if (! Schema::hasColumn('accounting_document_requests', 'checklist_items')) {
                    $table->json('checklist_items')->nullable()->after('protocol_code');
                }
                if (! Schema::hasColumn('accounting_document_requests', 'pending_items_count')) {
                    $table->unsignedSmallInteger('pending_items_count')->default(0)->after('checklist_items');
                }
                if (! Schema::hasColumn('accounting_document_requests', 'last_version_number')) {
                    $table->unsignedInteger('last_version_number')->default(0)->after('pending_items_count');
                }
                if (! Schema::hasColumn('accounting_document_requests', 'last_reminder_at')) {
                    $table->timestamp('last_reminder_at')->nullable()->after('last_version_number');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('accounting_document_requests')) {
            Schema::table('accounting_document_requests', function (Blueprint $table): void {
                if (Schema::hasColumn('accounting_document_requests', 'template_id')) {
                    $table->dropConstrainedForeignId('template_id');
                }

                foreach (['protocol_code', 'checklist_items', 'pending_items_count', 'last_version_number', 'last_reminder_at'] as $column) {
                    if (Schema::hasColumn('accounting_document_requests', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('accounting_obligations')) {
            Schema::table('accounting_obligations', function (Blueprint $table): void {
                foreach (['stage_code', 'assigned_to_name', 'started_at', 'reminder_at', 'last_reminder_at'] as $column) {
                    if (Schema::hasColumn('accounting_obligations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('accounting_fee_entries')) {
            Schema::table('accounting_fee_entries', function (Blueprint $table): void {
                foreach (['recurrence_frequency', 'recurrence_interval', 'next_reference_date', 'adjustment_type', 'adjustment_value', 'reminder_email_enabled', 'reminder_whatsapp_enabled', 'overdue_notified_at'] as $column) {
                    if (Schema::hasColumn('accounting_fee_entries', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        Schema::dropIfExists('accounting_reminder_logs');
        Schema::dropIfExists('accounting_document_versions');
        Schema::dropIfExists('accounting_task_histories');
        Schema::dropIfExists('accounting_service_templates');
        Schema::dropIfExists('accounting_client_profiles');
    }

    private function ensureForeignKey(
        string $tableName,
        string $columnName,
        string $referencedTable,
        string $referencedColumn,
        string $constraintName,
        string $onDelete,
    ): void {
        if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, $columnName)) {
            return;
        }

        if ($this->hasForeignKeyForColumn($tableName, $columnName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columnName, $referencedTable, $referencedColumn, $constraintName, $onDelete): void {
            $foreign = $table
                ->foreign($columnName, $constraintName)
                ->references($referencedColumn)
                ->on($referencedTable);

            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
                return;
            }

            if ($onDelete === 'cascade') {
                $foreign->cascadeOnDelete();
                return;
            }

            $foreign->onDelete($onDelete);
        });
    }

    private function hasForeignKeyForColumn(string $tableName, string $columnName): bool
    {
        return DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $tableName)
            ->where('COLUMN_NAME', $columnName)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->exists();
    }
};
