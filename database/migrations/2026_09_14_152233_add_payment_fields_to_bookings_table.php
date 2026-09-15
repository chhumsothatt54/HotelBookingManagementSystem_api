<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('bookings', function (Blueprint $table) {
        $table->string('payment_status')->default('pending'); // pending, paid
        $table->string('md5_hash')->nullable(); // ទុកសម្រាប់ផ្ទៀងផ្ទាត់ Bakong
        $table->string('qr_string')->nullable(); // 
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            //
        });
    }
};
