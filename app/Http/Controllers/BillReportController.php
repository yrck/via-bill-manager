<?php

namespace App\Http\Controllers;

use App\Billing\BillReport;
use App\Models\User;
use Illuminate\Http\Request;

class BillReportController extends Controller
{
    public function show(Request $request, int $organization)
    {
        return $this->showFor($request, $request->user(), $organization);
    }

    public function export(Request $request, int $organization)
    {
        return $this->showFor($request, $request->user(), $organization, null, true);
    }

    public function showFor(Request $request, User $user, int $organization, ?object $customerView = null, bool $export = false)
    {
        $data = app(BillReport::class)->data($user, $organization, $request->query());
        if (! $export) {
            return response()->view('customer.report', $data + ['customerView' => $customerView])->header('Cache-Control', 'no-store, private');
        }

        // Materialized under fresh authorization above; no deferred queries or public files.
        return response()->streamDownload(function () use ($data) {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Generated at', 'From reporting month', 'To reporting month', 'Property filter', 'Status filter', 'Bill ID', 'Reporting month', 'Location', 'Utility account', 'Provider', 'Commodity', 'Status', 'Statement version', 'Invoice', 'Currency', 'Current charges', 'Printed prior balance', 'Printed amount due', 'Usage kWh', 'Service start', 'Service end', 'Expected by', 'Grace through', 'Received on', 'Due on'], ',', '"', '');
            foreach ($data['rows'] as $row) {
                fputcsv($output, [
                    $data['generatedAt'], $data['filters']['from'], $data['filters']['to'], $data['filters']['location'] ?? 'all accessible', $data['filters']['status'] ?? 'all',
                    $row->id, $row->period, BillReport::text($row->location), BillReport::text($row->reference), BillReport::text($row->supplier), BillReport::text($row->commodity),
                    $row->display_status, $row->revision_number, BillReport::text($row->invoice_number), $row->currency,
                    BillReport::money($row->charges_cents), BillReport::money($row->balance_forward_cents), BillReport::money($row->amount_due_cents), $row->usage_kwh,
                    $row->service_start, $row->service_end, $row->is_excluded || $row->expectation_source === 'intake' ? '' : $row->expected_by,
                    $row->is_excluded ? '' : $row->missing_after, $row->received_on, $row->due_on,
                ], ',', '"', '');
            }
            fclose($output);
        }, 'bill-report-'.$data['filters']['from'].'-to-'.$data['filters']['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }
}
