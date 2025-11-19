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
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('fee_invoice_id')->nullable()->constrained('fee_invoices')->nullOnDelete();
            $table->date('payment_date');
            $table->string('method');
            $table->decimal('amount', 10, 2);
            $table->enum('status', ['pending', 'completed', 'failed', 'refunded'])->default('completed');
            $table->string('reference_no')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
