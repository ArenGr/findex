<?php

use App\Models\ExchangeQuoteResponse;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run the migrations.
    public function up(): void
    {
        Schema::create('exchange_quote_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('response_token', 64)->unique();
            $table->string('status', 20)->default(ExchangeQuoteResponse::STATUS_PENDING);
            $table->decimal('posted_rate', 12, 4);
            $table->decimal('offered_rate', 12, 4)->nullable();
            $table->text('reply_text')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['exchange_quote_request_id', 'organization_id'], 'exchange_quote_responses_request_org_unique');
        });
    }

    // Reverse the migrations.
    public function down(): void
    {
        Schema::dropIfExists('exchange_quote_responses');
    }
};
