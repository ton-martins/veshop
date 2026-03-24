<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('profile_key', 80)->default('overview');
            $table->string('scope', 30)->default('global');
            $table->string('name', 120)->nullable();
            $table->string('niche', 30)->nullable();
            $table->string('business_type', 60)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['profile_key', 'scope', 'is_active']);
            $table->index(['contractor_id', 'profile_key', 'is_active']);
            $table->index(['plan_id', 'profile_key', 'is_active']);
            $table->index(['niche', 'business_type', 'profile_key'], 'report_profiles_niche_business_profile_idx');
        });

        Schema::create('report_metric_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->string('profile_key', 80)->default('overview');
            $table->string('metric_key', 80);
            $table->string('niche', 30)->nullable();
            $table->string('business_type', 60)->nullable();
            $table->string('granularity', 20)->default('day');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('dimension_type', 40)->nullable();
            $table->string('dimension_key', 120)->nullable();
            $table->string('dimension_label', 180)->nullable();
            $table->decimal('value_decimal', 14, 2)->nullable();
            $table->bigInteger('value_integer')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(
                ['contractor_id', 'metric_key', 'period_start', 'period_end'],
                'report_metric_snapshots_contractor_metric_period_idx'
            );
            $table->index(
                ['niche', 'business_type', 'metric_key', 'period_start', 'period_end'],
                'report_metric_snapshots_niche_metric_period_idx'
            );
            $table->index(['plan_id', 'metric_key', 'period_start', 'period_end'], 'report_metric_snapshots_plan_metric_period_idx');
            $table->index(['profile_key', 'metric_key', 'granularity'], 'report_metric_snapshots_profile_metric_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_metric_snapshots');
        Schema::dropIfExists('report_profiles');
    }
};
