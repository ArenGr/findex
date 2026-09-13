<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_suggestions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_response_id')->constrained()->cascadeOnDelete();
            $table->decimal('price_amount', 10, 2);
            $table->string('price_currency', 3);
            $table->string('offered_hotel_name')->nullable();
            $table->text('flight_details')->nullable();
            $table->text('inclusions')->nullable();
            $table->string('attachment_path')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('quote_responses')->whereNotNull('price_amount')->get() as $response) {
            DB::table('quote_suggestions')->insert([
                'quote_response_id' => $response->id,
                'price_amount' => $response->price_amount,
                'price_currency' => $response->price_currency,
                'offered_hotel_name' => $response->offered_hotel_name,
                'flight_details' => $response->flight_details,
                'inclusions' => $response->inclusions,
                'attachment_path' => $response->attachment_path,
                'created_at' => $response->responded_at ?? $response->created_at,
                'updated_at' => $response->responded_at ?? $response->created_at,
            ]);
        }

        Schema::table('quote_responses', function (Blueprint $table) {
            $table->dropColumn([
                'price_amount',
                'price_currency',
                'offered_hotel_name',
                'flight_details',
                'inclusions',
                'attachment_path',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('quote_responses', function (Blueprint $table) {
            $table->decimal('price_amount', 10, 2)->nullable();
            $table->string('price_currency', 3)->nullable();
            $table->string('offered_hotel_name')->nullable();
            $table->text('flight_details')->nullable();
            $table->text('inclusions')->nullable();
            $table->string('attachment_path')->nullable();
        });

        foreach (DB::table('quote_suggestions')->orderBy('price_amount')->get() as $suggestion) {
            DB::table('quote_responses')->where('id', $suggestion->quote_response_id)->whereNull('price_amount')->update([
                'price_amount' => $suggestion->price_amount,
                'price_currency' => $suggestion->price_currency,
                'offered_hotel_name' => $suggestion->offered_hotel_name,
                'flight_details' => $suggestion->flight_details,
                'inclusions' => $suggestion->inclusions,
                'attachment_path' => $suggestion->attachment_path,
            ]);
        }

        Schema::dropIfExists('quote_suggestions');
    }
};
