<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborators', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->string('name', 180);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('job_title', 120)->nullable();
            $table->string('photo_url', 2048)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contractor_id', 'name']);
            $table->index(['contractor_id', 'is_active']);
        });

        Schema::create('collaborator_service_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('collaborator_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collaborator_id', 'service_category_id'], 'collab_srv_cat_unique');
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->foreignId('collaborator_id')
                ->nullable()
                ->after('service_catalog_id')
                ->constrained('collaborators')
                ->nullOnDelete();

            $table->index(['contractor_id', 'collaborator_id', 'scheduled_for'], 'service_orders_contractor_collaborator_scheduled_idx');
        });

        Schema::table('service_appointments', function (Blueprint $table): void {
            $table->foreignId('collaborator_id')
                ->nullable()
                ->after('service_catalog_id')
                ->constrained('collaborators')
                ->nullOnDelete();

            $table->index(['contractor_id', 'collaborator_id', 'starts_at'], 'service_appointments_contractor_collaborator_starts_idx');
        });
    }

    public function down(): void
    {
        Schema::table('service_appointments', function (Blueprint $table): void {
            $table->dropIndex('service_appointments_contractor_collaborator_starts_idx');
            $table->dropConstrainedForeignId('collaborator_id');
        });

        Schema::table('service_orders', function (Blueprint $table): void {
            $table->dropIndex('service_orders_contractor_collaborator_scheduled_idx');
            $table->dropConstrainedForeignId('collaborator_id');
        });

        Schema::dropIfExists('collaborator_service_category');
        Schema::dropIfExists('collaborators');
    }
};
