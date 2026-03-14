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
        Schema::table('contractors', function (Blueprint $table): void {
            $table->foreignId('plan_id')
                ->nullable()
                ->after('slug')
                ->constrained('plans')
                ->nullOnDelete();

            $table->boolean('is_active')
                ->default(true)
                ->after('settings')
                ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn('is_active');
        });
    }
};
