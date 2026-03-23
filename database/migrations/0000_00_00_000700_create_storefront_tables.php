<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_customers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 160);
            $table->string('email')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('cep', 9)->nullable();
            $table->string('street', 160)->nullable();
            $table->string('number', 20)->nullable();
            $table->string('complement', 120)->nullable();
            $table->string('neighborhood', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 2)->nullable();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['contractor_id', 'email']);
            $table->index(['contractor_id', 'is_active']);
            $table->index(['contractor_id', 'phone']);
        });

        Schema::create('shop_customer_favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contractor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_customer_id')->constrained('shop_customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shop_customer_id', 'product_id'], 'shop_customer_favorites_unique');
            $table->index(['contractor_id', 'shop_customer_id'], 'shop_customer_favorites_contractor_customer_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_customer_favorites');
        Schema::dropIfExists('shop_customers');
    }
};
