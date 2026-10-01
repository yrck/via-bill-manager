<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_exceptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expected_bill_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status')->default('open');
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assignee_name')->nullable();
            $table->string('next_action', 500);
            $table->date('due_on');
            $table->unsignedInteger('source_revision')->default(0);
            $table->unsignedInteger('version')->default(1);
            $table->timestamps();
            $table->index(['status', 'due_on']);
        });
        Schema::create('bill_exception_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_exception_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('action');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name');
            $table->text('note');
            $table->json('before_state')->nullable();
            $table->json('after_state');
            $table->timestamp('created_at');
            $table->unique(['bill_exception_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_exception_events');
        Schema::dropIfExists('bill_exceptions');
    }
};
