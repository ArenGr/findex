<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The owner's passport/ID/PSC number is no longer stored.
     *
     * It was only ever an input: insurers price compulsory motor TPL by
     * looking the vehicle and the owner's bonus-malus class up in the Motor
     * Insurers' Bureau registry themselves (see IngoAppaProvider), so once a
     * premium comes back nothing here reads the number again. Keeping it was
     * retention without a purpose, and it is the most sensitive field in the
     * database. It now travels as a QuoteIdentity for the length of one
     * request and is never written down.
     *
     * Rows created before this ran are dropping a real ID number, which is
     * the point - down() can restore the column but not the values, and
     * deliberately so.
     */
    public function up(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->dropColumn('owner_id_number');
        });
    }

    public function down(): void
    {
        Schema::table('auto_insurance_requests', function (Blueprint $table) {
            $table->string('owner_id_number')->nullable()->after('owner_type');
        });
    }
};
