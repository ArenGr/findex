<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->text('excerpt')->nullable()->after('title');
            $table->string('featured_image')->nullable()->after('body');
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('reviewed_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable()->after('reviewed_by');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn(['excerpt', 'featured_image', 'rejection_reason', 'published_at']);
        });
    }
};
