<?php

namespace App\Billing;

use App\Access\BillingAccess;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class CustomerBills
{
    public function query(User $user, int $organization, bool $includeExcluded = false): Builder
    {
        return DB::table('expected_bills as e')
            ->whereIn('e.id', app(BillingAccess::class)->expectedBills($user, $organization)->select('expected_bills.id'))
            ->join('utility_accounts as a', 'a.id', '=', 'e.utility_account_id')
            ->join('locations as l', 'l.id', '=', 'a.location_id')
            ->leftJoin('statements as s', 's.expected_bill_id', '=', 'e.id')
            ->when(! $includeExcluded, fn ($query) => $query->where(fn ($query) => $query->where('e.is_excluded', false)->orWhereNotNull('s.id')))
            ->select(['e.id', 'e.period', 'e.expected_by', 'a.reference', 'a.supplier', 'a.commodity', 'l.name as location', 'l.id as location_id', 's.id as statement_id', 's.charges_cents', 's.currency', 's.received_on', 's.due_on', 's.status', 's.title', 's.evidence', 's.review_version', 's.invoice_number', 's.issued_on', 's.service_start', 's.service_end', 's.balance_forward_cents', 's.amount_due_cents', 's.usage_kwh', 'e.expectation_source'])
            ->addSelect('s.revision_number', 'e.utility_account_id', 'e.missing_after', 'e.is_excluded')
            ->selectRaw("CASE WHEN s.id IS NOT NULL THEN s.status WHEN e.is_excluded = true THEN 'excluded' WHEN e.expectation_source != 'intake' AND COALESCE(e.missing_after, e.expected_by) < ? THEN 'missing' ELSE 'awaiting' END as display_status", [today()->toDateString()]);
    }
}
