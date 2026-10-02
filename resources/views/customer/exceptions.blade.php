<x-customer-layout :customer-view="$customerView"><main>
<a class="back-link" href="{{ $customerView ? route('platform.customer-view') : route('customer.workspace', $organization->id) }}">← Workspace</a>
<div class="heading"><div><div class="eyebrow">{{ $organization->name }}</div><h1>Investigations &amp; follow-ups</h1><p>Assigned work across your accessible properties. Open an investigation from any bill or missing statement.</p></div></div>
<form class="panel review-form" method="GET">
<label for="case-state">Status</label><select id="case-state" name="state"><option value="open">Open</option><option value="resolved" @selected(($filters['state'] ?? '') === 'resolved')>Resolved</option></select>
<label for="case-assignment">Assignment</label><select id="case-assignment" name="assignment"><option value="">Everyone</option><option value="mine" @selected(($filters['assignment'] ?? '') === 'mine')>Assigned to {{ $customerView ? 'selected customer' : 'me' }}</option><option value="unassigned" @selected(($filters['assignment'] ?? '') === 'unassigned')>Unassigned</option></select>
<label><input type="checkbox" name="overdue" value="1" @checked($filters['overdue'] ?? false)> Overdue open work only (UTC)</label><button class="primary-button">Apply filters</button>
</form>
<section class="panel"><div class="panel-heading"><h2>{{ $exceptions->total() }} investigations</h2><span>Earliest follow-up first</span></div>
@forelse($exceptions as $item)<article class="review-event"><div><strong>{{ $item->location }} · {{ $item->reference }} · {{ \Carbon\CarbonImmutable::parse($item->period)->format('M Y') }}</strong><span>{{ ucfirst($item->exception_status) }} · Due {{ $item->action_due }}</span></div><p>{{ $item->next_action }}</p><p>{{ $item->assignee_name ?? 'Unassigned' }} @if($item->exception_status === 'open' && $item->action_due < today()->toDateString()) · Overdue @endif @if((int) $item->source_revision !== (int) $item->revision_number || (int) $item->source_validation_run_id !== (int) $item->validation_run_id) · Source statement or validation changed @endif</p><a class="inspect" href="{{ ($customerView ? route('platform.customer-view.bill', $item->id) : route('customer.bills.show', [$organization->id, $item->id])).'#investigation' }}">View investigation →</a></article>
@empty<div class="empty">No investigations match these filters.</div>@endforelse
<div class="pagination">{{ $exceptions->links() }}</div></section>
</main></x-customer-layout>
