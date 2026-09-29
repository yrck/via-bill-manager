<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolios', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->unique(['portfolio_id', 'name']);
        });
        Schema::create('utility_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('reference');
            $table->string('commodity');
            $table->string('supplier');
            $table->unique(['location_id', 'reference']);
        });
        Schema::create('expected_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('utility_account_id')->constrained()->restrictOnDelete();
            $table->date('period');
            $table->date('expected_by');
            $table->unique(['utility_account_id', 'period']);
        });
        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expected_bill_id')->unique()->constrained()->restrictOnDelete();
            $table->bigInteger('charges_cents');
            $table->string('currency', 3)->default('USD');
            $table->date('received_on');
            $table->date('due_on')->nullable();
            $table->string('status');
            $table->string('title');
            $table->text('evidence');
            $table->string('owner');
        });
    }

    public function down(): void
    {
        foreach (['statements', 'expected_bills', 'utility_accounts', 'locations', 'portfolios'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
