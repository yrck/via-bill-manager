<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_validation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('source_revision');
            $table->string('rules_version');
            $table->string('fingerprint', 64);
            $table->json('inputs');
            $table->json('findings');
            $table->timestamp('created_at');
            $table->unique(['statement_id', 'fingerprint']);
        });
        Schema::table('bill_exceptions', function (Blueprint $table) {
            $table->foreignId('source_validation_run_id')->nullable()->constrained('bill_validation_runs')->nullOnDelete();
        });
        Schema::table('statements', function (Blueprint $table) {
            $table->foreignId('validation_run_id')->nullable()->constrained('bill_validation_runs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bill_exceptions', fn (Blueprint $table) => $table->dropConstrainedForeignId('source_validation_run_id'));
        Schema::table('statements', fn (Blueprint $table) => $table->dropConstrainedForeignId('validation_run_id'));
        Schema::dropIfExists('bill_validation_runs');
    }
};
