<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statements', fn (Blueprint $table) => $table->unsignedInteger('revision_number')->default(1));
        Schema::table('customer_review_decisions', fn (Blueprint $table) => $table->unsignedInteger('revision_number')->default(1));
        Schema::table('bill_documents', function (Blueprint $table) {
            $table->dropUnique(['statement_id']);
            $table->index('statement_id');
        });
        Schema::create('statement_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->json('snapshot');
            $table->foreignId('document_id')->nullable()->constrained('bill_documents')->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_name')->nullable();
            $table->text('reason');
            $table->timestamp('created_at');
            $table->unique(['statement_id', 'number']);
        });
        Schema::create('statement_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_revision_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('description');
            $table->string('category');
            $table->decimal('quantity', 20, 6)->nullable();
            $table->string('unit', 40)->nullable();
            $table->decimal('rate', 20, 8)->nullable();
            $table->string('rate_unit', 40)->nullable();
            $table->bigInteger('amount_cents');
            $table->string('source_reference')->nullable();
            $table->unique(['statement_revision_id', 'position']);
        });
        DB::table('statements')->orderBy('id')->chunkById(200, function ($statements) {
            foreach ($statements as $statement) {
                $document = DB::table('bill_documents')->where('statement_id', $statement->id)->first();
                DB::table('statement_revisions')->insert([
                    'statement_id' => $statement->id, 'number' => 1, 'snapshot' => json_encode($statement, JSON_THROW_ON_ERROR),
                    'document_id' => $document?->id, 'reason' => 'Existing statement preserved when version history was introduced.',
                    'created_at' => now(),
                ]);
            }
        });
    }

    public function down(): void
    {
        // Multiple source documents cannot fit the previous schema without losing evidence.
        if (DB::table('statement_revisions')->where('number', '>', 1)->exists()) {
            throw new RuntimeException('Restore a pre-migration backup to roll back after statement corrections.');
        }
        Schema::dropIfExists('statement_line_items');
        Schema::dropIfExists('statement_revisions');
        Schema::table('bill_documents', function (Blueprint $table) {
            $table->dropIndex(['statement_id']);
            $table->unique('statement_id');
        });
        Schema::table('customer_review_decisions', fn (Blueprint $table) => $table->dropColumn('revision_number'));
        Schema::table('statements', fn (Blueprint $table) => $table->dropColumn('revision_number'));
    }
};
