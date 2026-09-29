<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Identity alone does not grant application access.
            $table->boolean('is_active')->default(false);
        });
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key')->unique();
            $table->timestamps();
        });
        Schema::table('portfolios', function (Blueprint $table) {
            // Existing fictional portfolios stay unowned and outside authenticated access.
            $table->foreignId('organization_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->enum('role', ['viewer', 'reviewer'])->default('viewer');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
        });
        Schema::create('location_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_membership_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_grants');
        Schema::dropIfExists('organization_memberships');
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
        });
        Schema::dropIfExists('organizations');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_active'));
    }
};
