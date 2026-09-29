<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_memberships', function (Blueprint $table) {
            $table->unsignedInteger('access_version')->default(0);
        });
        Schema::create('membership_access_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->unsignedInteger('version');
            $table->json('before');
            $table->json('after');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->unique(['organization_membership_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_access_changes');
        Schema::table('organization_memberships', fn (Blueprint $table) => $table->dropColumn('access_version'));
    }
};
