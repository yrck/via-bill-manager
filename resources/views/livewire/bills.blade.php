<div>
@include('partials.workspace-nav')
<main>
    <div class="heading"><div><div class="eyebrow">SEPTEMBER 2026 · FICTIONAL PORTFOLIO</div><h1>A place for every bill.</h1><p>Track expected statements, investigate findings, and review received charges.</p></div><span class="pill">{{ $rows->count() }} of {{ $total }} records</span></div>
    <div class="toolbar bill-filters">
        <label>Property<select wire:model.live="scope"><option value="all">All properties</option>@foreach($properties as $property)<option value="{{ $property }}">{{ $property }}</option>@endforeach</select></label>
        <label>Status<select wire:model.live="status"><option value="all">All statuses</option><option value="review">Needs review</option><option value="missing">Missing</option><option value="verified">Verified</option><option value="awaiting">Not yet expected</option></select></label>
        <label class="search-field">Find an account<input type="search" wire:model.live.debounce.250ms="search" placeholder="Account, supplier or property" maxlength="200"></label>
    </div>
    <section class="panel"><div class="panel-heading"><div><h2>Bill register</h2><p>One row per expected statement · amounts in USD</p></div><span wire:loading.delay role="status" class="muted">Updating…</span></div>
        <div class="table-scroll"><table class="account-table bill-table"><caption class="sr-only">Expected September statements</caption><thead><tr><th scope="col">Account / location</th><th scope="col">Supplier / service</th><th scope="col">Status</th><th scope="col">Charges</th><th scope="col">Timing</th><th scope="col">Details</th></tr></thead><tbody>
        @forelse($rows as $bill)
            <tr wire:key="bill-{{ $bill['id'] }}"><th scope="row">{{ $bill['account'] }}<small>{{ $bill['property'] }}</small></th><td>{{ $bill['vendor'] }}<small>{{ $bill['commodity'] }}</small></td><td><span class="status-badge {{ $bill['status'] }}">{{ ['review' => 'Needs review', 'missing' => 'Missing', 'verified' => 'Verified', 'awaiting' => 'Not yet expected'][$bill['status']] }}</span></td><td>{{ $bill['cents'] === null ? 'Unknown' : '$'.number_format($bill['cents'] / 100, 2) }}</td><td>{{ $bill['date'] }}</td><td><a class="inspect" href="{{ route('bills.show', $bill['id']) }}" aria-label="Open {{ $bill['account'] }}">Open →</a></td></tr>
        @empty<tr><td colspan="6" class="empty">No bills match these filters. Try another property, status or search.</td></tr>@endforelse
        </tbody></table></div>
    </section>
    <p class="portfolio-note">Missing statements have unknown charges. Verified statements may still be unpaid; payment status is unavailable in this demo.</p>
</main>
</div>
