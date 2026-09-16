<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visa_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('guest_name')->nullable();
            $table->string('guest_email')->nullable();
            $table->string('locale', 5)->nullable();
            $table->string('destination_country', 2);
            $table->date('travel_from');
            $table->date('travel_to');
            $table->unsignedTinyInteger('applicants')->default(1);
            $table->string('status', 20)->default('submitted');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['destination_country', 'expires_at']);
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        // A request belongs to someone: an account, or a guest we can email back.
        if (in_array(DB::getDriverName(), ['mysql', 'pgsql'], true)) {
            DB::statement('ALTER TABLE visa_requests ADD CONSTRAINT visa_requests_owner_check CHECK (user_id IS NOT NULL OR guest_email IS NOT NULL)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_requests');
    }
};
