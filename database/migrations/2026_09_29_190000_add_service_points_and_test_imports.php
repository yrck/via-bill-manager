<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->boolean('is_test_account')->default(false);
            $table->string('legal_name')->nullable();
            $table->text('billing_address')->nullable();
        });
        Schema::table('locations', function (Blueprint $table) {
            $table->text('service_address')->nullable();
        });
        Schema::create('service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('esi_id', 64);
            $table->string('label');
            $table->text('service_address');
            $table->string('source_document');
            $table->string('source_sha256', 64);
            $table->timestamps();
            $table->unique(['organization_id', 'esi_id']);
        });
        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_point_id')->constrained()->restrictOnDelete();
            $table->string('meter_number');
            $table->date('observed_on');
            $table->timestamps();
            $table->unique(['service_point_id', 'meter_number']);
        });
        Schema::create('utility_account_service_points', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_account_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_point_id')->constrained()->restrictOnDelete();
            $table->unique(['utility_account_id', 'service_point_id'], 'utility_service_point_unique');
        });
        Schema::create('test_account_imports', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key')->unique();
            $table->string('payload_sha256', 64);
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('owner_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_account_imports');
        Schema::dropIfExists('utility_account_service_points');
        Schema::dropIfExists('meters');
        Schema::dropIfExists('service_points');
        Schema::table('locations', fn (Blueprint $table) => $table->dropColumn('service_address'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn(['is_test_account', 'legal_name', 'billing_address']));
    }
};
