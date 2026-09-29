<?php

namespace App\Demo;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Fictional local review exercise, not approval/payment authorization. */
class ReviewStatement
{
    public function record(int $expectedBill, int $version, string $decision, string $note): void
    {
        abort_unless(app()->environment('local', 'testing') && config('demo.enabled'), 404);
        $note = trim($note);
        Validator::make(compact('note', 'decision'), [
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'decision' => ['required', 'in:verify,reopen'],
        ])->validate();

        DB::transaction(function () use ($expectedBill, $version, $decision, $note) {
            // Recheck the fixed demo boundary on every write. Real tenant policies remain future work.
            $row = app(BillingDataset::class)->statements()->firstWhere('id', $expectedBill);
            abort_unless($row && $row['statement_id'], 404);
            $statement = DB::table('statements')->where('id', $row['statement_id'])->lockForUpdate()->first();
            $from = $decision === 'verify' ? 'review' : 'verified';
            $to = $decision === 'verify' ? 'verified' : 'review';
            if ($statement->review_version !== $version || $statement->status !== $from) {
                throw ValidationException::withMessages(['decision' => 'This statement changed since you opened it. Reload the page and review the latest decision.']);
            }
            DB::table('statements')->where('id', $statement->id)->update([
                'status' => $to, 'review_version' => $version + 1,
            ]);
            DB::table('demo_review_decisions')->insert([
                'statement_id' => $statement->id, 'version' => $version + 1,
                'from_status' => $from, 'to_status' => $to, 'note' => $note, 'created_at' => now(),
            ]);
        });
    }
}
