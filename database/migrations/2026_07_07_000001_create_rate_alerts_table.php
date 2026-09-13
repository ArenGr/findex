<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run the migrations.
    public function up(): void
    {
        Schema::create('rate_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('rate_type'); // App\Enums\RateType value
            $table->string('rate_field'); // 'buy_rate' or 'sell_rate'
            $table->string('direction'); // 'above' or 'below'
            $table->decimal('threshold', 10, 4);
            $table->string('channel'); // 'email' or 'telegram'
            $table->string('telegram_chat_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_currently_met')->default(false);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'currency_id', 'rate_type']);
        });
    }

    // Reverse the migrations.
    public function down(): void
    {
        Schema::dropIfExists('rate_alerts');
    }
};
