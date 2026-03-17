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
        Schema::table('sales', function (Blueprint $table): void {
            $table->string('checkout_idempotency_key', 80)
                ->nullable()
                ->after('code');

            $table->unique(['contractor_id', 'checkout_idempotency_key'], 'sales_contractor_checkout_idempotency_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropUnique('sales_contractor_checkout_idempotency_unique');
            $table->dropColumn('checkout_idempotency_key');
        });
    }
};
