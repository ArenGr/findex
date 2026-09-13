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

            // A key belongs to whoever will be billed for it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');

            $table->string('prefix', 12)->unique();
            // SHA-256 of the full key.
            $table->string('token_hash', 64)->unique();

            $table->string('plan')->default('free');

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['revoked_at']);
        });

        // One row per key per day.
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
