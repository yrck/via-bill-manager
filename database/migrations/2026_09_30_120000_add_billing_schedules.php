<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_account_id')->constrained()->restrictOnDelete();
            $table->date('effective_month');
            $table->string('mode');
            $table->unsignedTinyInteger('receipt_day')->nullable();
            $table->unsignedTinyInteger('month_offset')->nullable();
            $table->unsignedTinyInteger('grace_days')->nullable();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('actor_name');
            $table->text('reason');
            $table->timestamp('created_at');
            $table->index(['utility_account_id', 'effective_month']);
        });
        Schema::table('expected_bills', function (Blueprint $table) {
            $table->foreignId('billing_schedule_id')->nullable()->constrained()->restrictOnDelete();
            $table->date('missing_after')->nullable();
            $table->boolean('is_excluded')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('expected_bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('billing_schedule_id');
            $table->dropColumn(['missing_after', 'is_excluded']);
        });
        Schema::dropIfExists('billing_schedules');
    }
};
