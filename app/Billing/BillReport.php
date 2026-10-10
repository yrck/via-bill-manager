<?php

namespace App\Billing;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillReport
{
    public const LIMIT = 5000;

    public function data(User $user, int $organization, array $input): array
    {
        $account = app(CustomerAccounts::class)->accessible($user)->where('o.id', $organization)->first();
        abort_unless($account, 404);
        $filters = Validator::make($input, [
            'from' => ['nullable', 'date_format:Y-m-01'],
            'to' => ['nullable', 'date_format:Y-m-01'],
            'location' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'in:review,verified,missing,awaiting'],
        ])->validate();
        $filters['from'] = $filters['from'] ?? today()->startOfYear()->toDateString();
        $filters['to'] = $filters['to'] ?? today()->startOfMonth()->toDateString();
        if ($filters['from'] > $filters['to']) {
            throw ValidationException::withMessages(['to' => 'The end month must be on or after the start month.']);
        }
        $query = app(CustomerBills::class)->query($user, $organization)->whereBetween('e.period', [$filters['from'], $filters['to']]);
        if ($location = $filters['location'] ?? null) {
            abort_unless(app(BillingAccess::class)->locations($user, $organization)->where('locations.id', $location)->exists(), 404);
            $query->where('l.id', $location);
        }
        if ($status = $filters['status'] ?? null) {
            if (in_array($status, ['review', 'verified'])) {
                $query->where('s.status', $status);
            } else {
                $query->whereNull('s.id');
                if ($status === 'missing') {
                    $query->where('e.expectation_source', '!=', 'intake')->whereRaw('COALESCE(e.missing_after, e.expected_by) < ?', [today()->toDateString()]);
                } else {
                    $query->where(fn ($q) => $q->where('e.expectation_source', 'intake')->orWhereRaw('COALESCE(e.missing_after, e.expected_by) >= ?', [today()->toDateString()]));
                }
            }
        }
        // Fetch once: rows and totals share exactly the same current projections.
        $rows = $query->orderBy('e.period')->orderBy('l.id')->orderBy('a.id')->orderBy('e.id')->limit(self::LIMIT + 1)->get();
        if ($rows->count() > self::LIMIT) {
            throw ValidationException::withMessages(['from' => 'This report exceeds 5,000 rows. Narrow the months or property; no partial report was generated.']);
        }
        $totals = $rows->whereNotNull('statement_id')->groupBy('currency')->map(fn ($group) => [
            'charges_cents' => $group->sum('charges_cents'), 'count' => $group->count(),
        ]);

        return ['organization' => $account, 'filters' => $filters, 'rows' => $rows, 'totals' => $totals,
            'received' => $rows->whereNotNull('statement_id')->count(),
            'unknown' => $rows->whereNull('statement_id')->count(),
            'generatedAt' => now()->utc()->format('Y-m-d H:i:s').' UTC',
            'locations' => app(BillingAccess::class)->locations($user, $organization)->orderBy('locations.name')->get()];
    }

    public static function money(?int $cents): string
    {
        if ($cents === null) {
            return '';
        }

        return ($cents < 0 ? '-' : '').intdiv(abs($cents), 100).'.'.str_pad((string) (abs($cents) % 100), 2, '0', STR_PAD_LEFT);
    }

    public static function text(?string $value): string
    {
        $value ??= '';

        // Neutralize spreadsheet formulas and preserve numeric-looking identifiers.
        return preg_match('/^[\s\x00-\x1f]*[=+@-]|^[\t\r\n]|^0\d|^\d{15,}$/u', $value) ? "'".$value : $value;
    }
}
