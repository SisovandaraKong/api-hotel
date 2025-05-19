<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->onDelete('cascade');
            $table->decimal('total_payment', 10, 2);
            $table->string('method_payment');
            $table->string('transaction_id')->nullable();
            $table->string('payment_status')->default('pending');
            $table->datetime('date_payment');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
