<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_pdv_active')->default(true)->after('is_active');
            $table->boolean('is_storefront_active')->default(true)->after('is_pdv_active');
            $table->index(['contractor_id', 'is_pdv_active'], 'products_contractor_pdv_active_idx');
            $table->index(['contractor_id', 'is_storefront_active'], 'products_contractor_storefront_active_idx');
        });

        DB::statement('UPDATE products SET is_pdv_active = is_active, is_storefront_active = is_active');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_contractor_pdv_active_idx');
            $table->dropIndex('products_contractor_storefront_active_idx');
            $table->dropColumn(['is_pdv_active', 'is_storefront_active']);
        });
    }
};
