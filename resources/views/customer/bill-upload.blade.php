<x-customer-layout>
@php
    $correction = $correction ?? null;
    $defaults = $correction ? (array) $correction : [];
    foreach (['current_charges' => 'charges_cents', 'balance_forward' => 'balance_forward_cents', 'amount_due' => 'amount_due_cents'] as $field => $column) {
        $defaults[$field] = isset($defaults[$column]) ? number_format($defaults[$column] / 100, 2, '.', '') : '';
    }
    $rows = old('line_items', isset($lineItems) ? $lineItems->map(fn ($item) => (array) $item + ['amount' => number_format($item->amount_cents / 100, 2, '.', '')])->all() : []);
@endphp
<main class="statement-intake">
<a class="back-link" href="{{ $correction ? route('customer.bills.show', [$organization->id, $correction->id]) : route('customer.bills', $organization->id) }}">← {{ $correction ? 'Current statement' : 'Bill register' }}</a>
<div class="eyebrow">{{ $correction ? 'STATEMENT CORRECTION' : 'STATEMENT INTAKE' }}</div>
<h1>{{ $correction ? 'Correct the record. Keep the evidence.' : 'Add a source bill.' }}</h1>
<p>{{ $correction ? 'Save a new version with a reason. Previous values, PDFs and review decisions remain available. The new version starts in Needs review.' : 'Upload an electricity PDF in USD and enter its printed values. The statement starts in Needs review. This step does not extract data automatically.' }}</p>
@include('partials.auth-feedback')
<form class="auth-form" data-charge-editor method="POST" enctype="multipart/form-data" action="{{ $correction ? route('customer.bills.correct.store', [$organization->id, $correction->id]) : route('customer.bills.store', $organization->id) }}">@csrf
@if($correction)<input type="hidden" name="version" value="{{ old('version', $correction->review_version) }}">@endif
<section class="panel intake-section"><h2>Source and billing period</h2>
<label for="utility-account">Utility account</label><select id="utility-account" name="utility_account_id" required><option value="">Choose an account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('utility_account_id', $defaults['utility_account_id'] ?? '') == $account->id)>{{ $account->reference }} · {{ $account->supplier }}</option>@endforeach</select>
<label for="document">{{ $correction ? 'Replacement PDF (optional)' : 'Source PDF' }}</label><input id="document" type="file" name="document" accept="application/pdf" @required(!$correction)><small>PDF, up to 8 MiB. {{ $correction ? 'Leave empty to correct entered values using the current PDF.' : 'Select the file again after a validation error.' }}</small>
<div class="intake-fields">
@foreach(['invoice_number' => 'Invoice number', 'period' => 'Reporting month (YYYY-MM-01)', 'issued_on' => 'Issue date', 'service_start' => 'Service start', 'service_end' => 'Service end', 'due_on' => 'Due date', 'current_charges' => 'Current charges (USD)', 'balance_forward' => 'Balance forward / credit (USD)', 'amount_due' => 'Amount due (USD)', 'usage_kwh' => 'Usage (kWh)'] as $field => $label)
<div><label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['issued_on','service_start','service_end','due_on']) ? 'date' : 'text' }}" value="{{ old($field, $defaults[$field] ?? '') }}" required maxlength="150" @readonly($correction && $field === 'period')></div>
@endforeach
</div><small>USD amounts need two decimal places, without commas or dollar signs. Credits are negative. A correction keeps the account and reporting month. Separate additional bills in the same month are not supported yet.</small></section>
<section class="panel intake-section"><h2>Detailed current charges</h2><p>Optional: enter every current-charge line from the PDF. Their total must match Current charges, excluding balance forward. Preserve the printed quantities and rates; the printed amount is authoritative.</p>
<div data-charge-rows>
@foreach($rows as $index => $row)
@include('customer.charge-row', ['index' => $index, 'row' => $row])
@endforeach
</div>
<template data-charge-template>@include('customer.charge-row', ['index' => '__INDEX__', 'row' => []])</template>
<button type="button" class="inspect" data-add-charge>Add charge line</button><small data-charge-announcement aria-live="polite"></small>
<noscript><p>Enable JavaScript to add or remove charge lines. Summary-only intake is still available.</p></noscript></section>
@if($correction)<section class="panel intake-section"><h2>Reason for correction</h2><label for="reason">What changed and why?</label><textarea id="reason" name="reason" required minlength="10" maxlength="2000" rows="3">{{ old('reason') }}</textarea><small>Your name, the time and this reason are saved with the new version.</small></section>@endif
<button class="primary-button" @disabled($accounts->isEmpty())>{{ $correction ? 'Save new version for review' : 'Upload for review' }}</button>
</form></main></x-customer-layout>
