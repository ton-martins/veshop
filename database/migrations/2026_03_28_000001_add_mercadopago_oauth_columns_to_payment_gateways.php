<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->string('mp_user_id', 80)->nullable()->after('credentials');
            $table->string('mp_public_key', 120)->nullable()->after('mp_user_id');
            $table->text('mp_access_token')->nullable()->after('mp_public_key');
            $table->text('mp_refresh_token')->nullable()->after('mp_access_token');
            $table->timestamp('mp_token_expires_at')->nullable()->after('mp_refresh_token');
            $table->text('mp_scope')->nullable()->after('mp_token_expires_at');
            $table->boolean('mp_live_mode')->nullable()->after('mp_scope');
            $table->string('mp_status', 30)->default('disconnected')->after('mp_live_mode');
            $table->timestamp('mp_connected_at')->nullable()->after('mp_status');
            $table->text('mp_last_error')->nullable()->after('mp_connected_at');
            $table->json('mp_metadata')->nullable()->after('mp_last_error');

            $table->index(['contractor_id', 'provider', 'mp_status'], 'payment_gateways_scope_provider_status_idx');
            $table->index(['contractor_id', 'mp_user_id'], 'payment_gateways_scope_mp_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('payment_gateways', function (Blueprint $table): void {
            $table->dropIndex('payment_gateways_scope_provider_status_idx');
            $table->dropIndex('payment_gateways_scope_mp_user_idx');

            $table->dropColumn([
                'mp_user_id',
                'mp_public_key',
                'mp_access_token',
                'mp_refresh_token',
                'mp_token_expires_at',
                'mp_scope',
                'mp_live_mode',
                'mp_status',
                'mp_connected_at',
                'mp_last_error',
                'mp_metadata',
            ]);
        });
    }
};

