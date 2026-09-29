<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->boolean('is_platform_staff')->default(false));
        Schema::table('organizations', fn (Blueprint $table) => $table->unsignedInteger('status_version')->default(0));
        Schema::create('organization_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->boolean('was_active');
            $table->boolean('is_active');
            $table->unsignedInteger('version');
            $table->text('reason');
            $table->timestampTz('created_at');
            $table->unique(['organization_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_status_changes');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('status_version'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_staff'));
    }
};
