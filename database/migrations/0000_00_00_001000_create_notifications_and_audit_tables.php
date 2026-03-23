<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        Schema::create('report_exports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50);
            $table->string('status', 30)->default('pending');
            $table->json('filters')->nullable();
            $table->string('queue_connection', 40)->nullable();
            $table->string('queue_name', 80)->nullable();
            $table->string('file_disk', 40)->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['contractor_id', 'type', 'status']);
            $table->index(['requested_by_user_id', 'created_at']);
        });

        Schema::create('security_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_role', 20)->nullable();
            $table->string('event', 120)->index();
            $table->string('severity', 20)->default('warning')->index();
            $table->string('request_method', 10)->nullable();
            $table->string('request_path', 255)->nullable();
            $table->string('route_name', 120)->nullable();
            $table->char('ip_hash', 64)->nullable()->index();
            $table->string('user_agent', 512)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('occurred_at')->useCurrent()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_audit_logs');
        Schema::dropIfExists('report_exports');
        Schema::dropIfExists('notifications');
    }
};
