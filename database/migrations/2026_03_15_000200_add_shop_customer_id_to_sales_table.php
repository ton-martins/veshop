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
            $table->foreignId('shop_customer_id')
                ->nullable()
                ->after('client_id')
                ->constrained('shop_customers')
                ->nullOnDelete();

            $table->index(['contractor_id', 'shop_customer_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('shop_customer_id');
        });
    }
};
