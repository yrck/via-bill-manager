<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->unsignedInteger('version');
            $table->string('from_status');
            $table->string('to_status');
            $table->text('note');
            $table->timestampTz('created_at');
            $table->unique(['statement_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_review_decisions');
    }
};
