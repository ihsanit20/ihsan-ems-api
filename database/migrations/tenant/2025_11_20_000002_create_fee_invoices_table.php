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
        Schema::create('fee_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('academic_session_id')->constrained('academic_sessions');
            $table->string('invoice_no')->unique();
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->decimal('total_discount', 10, 2)->default(0);
            $table->decimal('payable_amount', 10, 2)->default(0);
            $table->enum('status', ['pending', 'partial', 'paid', 'cancelled'])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_invoices');
    }
};
