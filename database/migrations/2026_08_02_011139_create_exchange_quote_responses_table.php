<?php

use App\Models\ExchangeQuoteResponse;
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
        Schema::create('exchange_quote_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exchange_quote_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            // The secure, unauthenticated respond-page credential - same
            // pattern as quote_responses.response_token (travel).
            $table->string('response_token', 64)->unique();
            $table->string('status', 20)->default(ExchangeQuoteResponse::STATUS_PENDING);
            // Snapshot of the organization's CASH rate for this currency at
            // the moment the request was sent, so the org negotiates
            // against what the customer actually saw - even if their live
            // rate has since moved.
            $table->decimal('posted_rate', 12, 4);
            // Null until responded; equals posted_rate if they just confirm
            // "keep as is", higher if they improve it.
            $table->decimal('offered_rate', 12, 4)->nullable();
            $table->text('reply_text')->nullable();
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();

            $table->unique(['exchange_quote_request_id', 'organization_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exchange_quote_responses');
    }
};
