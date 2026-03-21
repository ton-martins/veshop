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
        Schema::table('categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('contractor_id')
                ->constrained('categories')
                ->nullOnDelete();

            $table->index(['contractor_id', 'parent_id', 'is_active'], 'categories_contractor_parent_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_contractor_parent_active_idx');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};

