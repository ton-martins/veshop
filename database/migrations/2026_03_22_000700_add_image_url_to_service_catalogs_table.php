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
        if (! Schema::hasTable('service_catalogs')) {
            return;
        }

        Schema::table('service_catalogs', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_catalogs', 'image_url')) {
                $table->string('image_url', 2048)
                    ->nullable()
                    ->after('description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('service_catalogs')) {
            return;
        }

        Schema::table('service_catalogs', function (Blueprint $table): void {
            if (Schema::hasColumn('service_catalogs', 'image_url')) {
                $table->dropColumn('image_url');
            }
        });
    }
};

