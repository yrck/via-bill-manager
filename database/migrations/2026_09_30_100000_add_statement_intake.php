<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expected_bills', fn (Blueprint $table) => $table->string('expectation_source')->default('planned'));
        Schema::table('statements', function (Blueprint $table) {
            $table->string('invoice_number')->nullable();
            $table->date('issued_on')->nullable();
            $table->date('service_start')->nullable();
            $table->date('service_end')->nullable();
            $table->bigInteger('balance_forward_cents')->nullable();
            $table->bigInteger('amount_due_cents')->nullable();
            $table->decimal('usage_kwh', 18, 3)->nullable();
        });
        Schema::create('bill_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('statement_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('source');
            $table->string('sha256', 64);
            $table->string('path')->unique();
            $table->string('filename');
            $table->timestamp('created_at');
            $table->unique(['organization_id', 'sha256']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_documents');
        Schema::table('statements', fn (Blueprint $table) => $table->dropColumn(['invoice_number', 'issued_on', 'service_start', 'service_end', 'balance_forward_cents', 'amount_due_cents', 'usage_kwh']));
        Schema::table('expected_bills', fn (Blueprint $table) => $table->dropColumn('expectation_source'));
    }
};
