<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerBillReview
{
    public function record(User $user, int $organization, int $bill, array $data): void
    {
        DB::transaction(function () use ($user, $organization, $bill, $data) {
            // Serialize with account suspension and owner-managed membership changes.
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            abort_unless(app(CustomerAccounts::class)->accessible($user)->where('o.id', $organization)->exists(), 404);
            $statement = app(BillingAccess::class)->statements($user, $organization, true)->where('expected_bill_id', $bill)->lockForUpdate()->first();
            abort_unless($statement, 404);
            $from = $data['decision'] === 'verify' ? 'review' : 'verified';
            $to = $data['decision'] === 'verify' ? 'verified' : 'review';
            if ($statement->review_version !== (int) $data['version'] || $statement->status !== $from) {
                throw ValidationException::withMessages(['decision' => 'This statement changed since you opened it. Reload and review the latest version.']);
            }
            DB::table('statements')->where('id', $statement->id)->update(['status' => $to, 'review_version' => $statement->review_version + 1]);
            DB::table('customer_review_decisions')->insert([
                'statement_id' => $statement->id, 'actor_id' => $user->id, 'actor_name' => $user->name,
                'revision_number' => $statement->revision_number,
                'version' => $statement->review_version + 1, 'from_status' => $from, 'to_status' => $to,
                'note' => $data['note'], 'created_at' => now(),
            ]);
        });
    }
}
