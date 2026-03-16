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
            $table->string('shipping_mode', 20)->nullable()->after('change_amount');
            $table->decimal('shipping_amount', 12, 2)->default(0)->after('shipping_mode');
            $table->unsignedSmallInteger('shipping_estimate_days')->nullable()->after('shipping_amount');
            $table->json('shipping_address')->nullable()->after('shipping_estimate_days');

            $table->index(['contractor_id', 'shipping_mode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->dropIndex(['contractor_id', 'shipping_mode']);
            $table->dropColumn([
                'shipping_mode',
                'shipping_amount',
                'shipping_estimate_days',
                'shipping_address',
            ]);
        });
    }
};
