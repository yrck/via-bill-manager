<?php

namespace App\Demo;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Local fictional demo queries only; this is not tenant authorization. */
class BillingDataset
{
    public const PORTFOLIO = 'fictional-september-2026';

    public const AS_OF = '2026-09-29';

    public function accounts(): Collection
    {
        return DB::table('utility_accounts as a')
            ->join('locations as l', 'l.id', '=', 'a.location_id')
            ->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')
            ->where('p.key', self::PORTFOLIO)->whereNull('p.organization_id')
            ->orderBy('l.id')->orderBy('a.id')
            ->get(['a.id', 'a.reference', 'a.commodity', 'a.supplier', 'l.name as property']);
    }

    public function statements(): Collection
    {
        $asOf = CarbonImmutable::parse(self::AS_OF);

        return DB::table('expected_bills as e')
            ->join('utility_accounts as a', 'a.id', '=', 'e.utility_account_id')
            ->join('locations as l', 'l.id', '=', 'a.location_id')
            ->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')
            ->leftJoin('statements as s', function ($join) {
                $join->on('s.expected_bill_id', '=', 'e.id')->where('s.received_on', '<=', self::AS_OF);
            })
            ->where('p.key', self::PORTFOLIO)->whereNull('p.organization_id')->where('e.period', '2026-09-01')
            ->orderBy('e.id')
            ->get(['e.id', 'e.expected_by', 'l.name as property', 'a.reference as account', 'a.commodity', 'a.supplier as vendor', 's.id as statement_id', 's.charges_cents as cents', 's.status', 's.review_version', 's.title', 's.evidence', 's.owner', 's.received_on', 's.due_on'])
            ->map(function ($row) use ($asOf) {
                $received = $row->statement_id !== null;
                $status = $received ? $row->status : ($row->expected_by < self::AS_OF ? 'missing' : 'awaiting');
                $due = $row->due_on ? CarbonImmutable::parse($row->due_on) : null;

                return [
                    'id' => $row->id, 'statement_id' => $row->statement_id, 'version' => $row->review_version, 'property' => $row->property, 'account' => $row->account,
                    'commodity' => $row->commodity, 'vendor' => $row->vendor, 'status' => $status,
                    'title' => $status === 'verified' ? 'Statement verified' : (($status === 'review' && $row->title === 'Statement verified' ? 'Statement reopened for review' : $row->title) ?? ($status === 'missing' ? 'September statement missing' : 'Awaiting expected statement')),
                    'cents' => $row->cents, 'due_soon' => $due && $due->betweenIncluded($asOf, $asOf->addDays(7)),
                    'date' => $due ? 'Due '.$due->format('M j') : ($received ? 'Received '.CarbonImmutable::parse($row->received_on)->format('M j') : 'Expected '.CarbonImmutable::parse($row->expected_by)->format('M j')),
                    'owner' => $row->owner ?? 'Unassigned',
                    'evidence' => $row->evidence ?? 'No accepted statement matches this expected September obligation. The expected-by date includes the demo grace period.',
                    'next' => $received ? 'Compare the finding with the source statement and document a resolution.' : 'Check collection history and locate the expected statement.',
                ];
            });
    }
}
