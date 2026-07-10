<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            // Guests can submit a request same as guest reviews (see the
            // reviews table), but a contact email is required for them since
            // there's no account to notify through as replies arrive.
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('destination_country', 2);
            $table->string('hotel_name')->nullable();
            $table->date('check_in');
            $table->date('check_out');
            $table->unsignedTinyInteger('adults');
            $table->unsignedTinyInteger('children')->default(0);
            $table->boolean('all_inclusive')->default(false);
            $table->boolean('insurance')->default(false);
            $table->text('notes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['destination_country', 'expires_at']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_guest_email_or_user_id CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL)');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE quote_requests ADD CONSTRAINT quote_requests_guest_email_or_user_id CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_requests');
    }
};
