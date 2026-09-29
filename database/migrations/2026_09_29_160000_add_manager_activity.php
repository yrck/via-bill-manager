<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manager_access_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->boolean('is_manager');
            $table->timestampTz('created_at');
        });
        Schema::create('customer_view_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manager_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->text('reason');
            $table->timestampTz('started_at');
            $table->timestampTz('ended_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_view_sessions');
        Schema::dropIfExists('manager_access_changes');
    }
};
