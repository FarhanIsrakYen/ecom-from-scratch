<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->decimal('max_discount_amount', 12, 2)->nullable();
            $table->decimal('minimum_order_amount', 12, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('usage_per_user')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['code', 'status']);
            $table->index(['starts_at', 'expires_at']);
        });

        Schema::create('delivery_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('postal_code')->nullable();
            $table->decimal('charge', 12, 2);
            $table->boolean('is_default')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['country', 'state', 'city', 'postal_code']);
        });

        Schema::create('tax_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('country', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->decimal('rate', 8, 4);
            $table->boolean('is_default')->default(false)->index();
            $table->string('status')->default('active')->index();
            $table->timestamps();

            $table->index(['country', 'state', 'city']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('coupon_id')->nullable()->after('payment_status')->constrained()->nullOnDelete();
            $table->string('coupon_code')->nullable()->after('coupon_id');
        });

        Schema::create('coupon_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);
            $table->timestamps();

            $table->index(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('coupon_id');
            $table->dropColumn('coupon_code');
        });

        Schema::dropIfExists('tax_settings');
        Schema::dropIfExists('delivery_zones');
        Schema::dropIfExists('coupons');
    }
};
