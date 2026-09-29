<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', function (Blueprint $table) {
            $table->unsignedInteger('review_version')->default(0);
        });
        Schema::create('demo_review_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained()->restrictOnDelete();
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
        Schema::dropIfExists('demo_review_decisions');
        Schema::table('statements', fn (Blueprint $table) => $table->dropColumn('review_version'));
    }
};
