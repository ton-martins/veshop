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
        Schema::table('clients', function (Blueprint $table): void {
            $table->string('cep', 9)->nullable()->after('document');
            $table->string('street', 160)->nullable()->after('cep');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement', 120)->nullable()->after('number');
            $table->string('neighborhood', 120)->nullable()->after('complement');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('cep', 9)->nullable()->after('document');
            $table->string('street', 160)->nullable()->after('cep');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement', 120)->nullable()->after('number');
            $table->string('neighborhood', 120)->nullable()->after('complement');
            $table->string('city', 120)->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
        });

        Schema::table('shop_customers', function (Blueprint $table): void {
            $table->string('cep', 9)->nullable()->after('phone');
            $table->string('street', 160)->nullable()->after('cep');
            $table->string('number', 20)->nullable()->after('street');
            $table->string('complement', 120)->nullable()->after('number');
            $table->string('neighborhood', 120)->nullable()->after('complement');
            $table->string('city', 120)->nullable()->after('neighborhood');
            $table->string('state', 2)->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_customers', function (Blueprint $table): void {
            $table->dropColumn([
                'cep',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn([
                'cep',
                'street',
                'number',
                'complement',
                'neighborhood',
                'city',
                'state',
            ]);
        });

        Schema::table('clients', function (Blueprint $table): void {
            $table->dropColumn([
                'cep',
                'street',
                'number',
                'complement',
                'neighborhood',
            ]);
        });
    }
};
