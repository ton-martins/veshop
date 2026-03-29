<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_appointments', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_appointments', 'payment_status')) {
                $table->string('payment_status', 30)->default('pending')->after('status');
                $table->index(['contractor_id', 'payment_status']);
            }

            if (! Schema::hasColumn('service_appointments', 'paid_at')) {
                $table->timestamp('paid_at')->nullable()->after('notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_appointments', function (Blueprint $table): void {
            if (Schema::hasColumn('service_appointments', 'payment_status')) {
                $table->dropIndex('service_appointments_contractor_id_payment_status_index');
                $table->dropColumn('payment_status');
            }

            if (Schema::hasColumn('service_appointments', 'paid_at')) {
                $table->dropColumn('paid_at');
            }
        });
    }
};
