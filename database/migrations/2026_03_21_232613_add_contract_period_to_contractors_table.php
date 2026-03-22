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
            $table->date('contract_starts_at')
                ->nullable()
                ->after('plan_id');

            $table->date('contract_ends_at')
                ->nullable()
                ->after('contract_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table): void {
            $table->dropColumn([
                'contract_starts_at',
                'contract_ends_at',
            ]);
        });
    }
};
