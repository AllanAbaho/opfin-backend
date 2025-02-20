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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('loan_product_id')->constrained();
            $table->foreignId('loan_product_term_id')->constrained();
            $table->foreignId('institution_id')->constrained();
            $table->foreignId('loan_application_id')->constrained();
            $table->unsignedBigInteger('amount');
            $table->string('status')->default('Pending');
            $table->string('reason')->nullable();
            $table->string('disbursed_at')->nullable();
            $table->integer('duration');
            $table->decimal('interest_rate');
            $table->string('interest_type');
            $table->unsignedBigInteger('repayment_amount');
            $table->string('repayment_start_date');
            $table->string('repayment_end_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
