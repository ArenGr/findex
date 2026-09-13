<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run the migrations.
    public function up(): void
    {
        Schema::create('exchange_quote_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('locale', 5);
            $table->foreignId('currency_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('rate_field', 20);
            $table->text('notes')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['currency_id', 'expires_at']);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'pgsql') {
            DB::statement('ALTER TABLE exchange_quote_requests ADD CONSTRAINT exchange_quote_requests_guest_email_or_user_id CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL)');
        }
    }

    // Reverse the migrations.
    public function down(): void
    {
        Schema::dropIfExists('exchange_quote_requests');
    }
};
