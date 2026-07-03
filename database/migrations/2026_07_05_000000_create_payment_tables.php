<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_checkout_session_id')->nullable()->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('usd');
            $table->string('status')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'provider', 'status']);
        });

        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider')->index();
            $table->string('provider_event_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_checkout_session_id')->nullable()->index();
            $table->string('type')->index();
            $table->string('status')->index();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('processed_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider');
            $table->string('event_id');
            $table->string('event_type');
            $table->timestamp('processed_at');
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('processed_webhook_events');
        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payments');
    }
};
