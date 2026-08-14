<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();

            // A key belongs to whoever will be billed for it. Both are nullable
            // because an internal key belongs to neither.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');

            // The first characters, shown in the dashboard so a customer can
            // tell two keys apart without us ever storing the key itself.
            $table->string('prefix', 12)->unique();
            // SHA-256 of the full key. We cannot recover it, only recognise it -
            // if a customer loses their key they get a new one, which is the
            // correct answer rather than an inconvenience.
            $table->string('token_hash', 64)->unique();

            // Names a plan in config/api.php rather than storing the limits, so
            // repricing a plan does not mean rewriting every row that bought it.
            $table->string('plan')->default('free');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['revoked_at']);
        });

        // One row per key per day. Deliberately not a log of every request:
        // usage reporting needs counts, and keeping request-level rows for a
        // busy customer would cost far more than the answer is worth.
        Schema::create('api_key_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_key_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedBigInteger('requests')->default(0);
            $table->timestamps();

            $table->unique(['api_key_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_usages');
        Schema::dropIfExists('api_keys');
    }
};
