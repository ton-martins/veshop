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
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_pdv_featured')
                ->default(false)
                ->after('is_active');

            $table->unsignedTinyInteger('pdv_featured_order')
                ->nullable()
                ->after('is_pdv_featured');

            $table->index(['contractor_id', 'is_pdv_featured', 'pdv_featured_order'], 'products_pdv_featured_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_pdv_featured_idx');
            $table->dropColumn(['is_pdv_featured', 'pdv_featured_order']);
        });
    }
};

