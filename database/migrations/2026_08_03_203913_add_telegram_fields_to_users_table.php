<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Run the migrations.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->after('avatar');
            $table->string('telegram_connect_token')->nullable()->unique()->after('telegram_chat_id');
            $table->string('locale', 5)->nullable()->after('telegram_connect_token');
        });
    }

    // Reverse the migrations.
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_connect_token', 'locale']);
        });
    }
};
