<x-customer-layout><main class="auth-page">
<a class="back-link" href="{{ route('customer.bills', $organization->id) }}">← Bill register</a><div class="eyebrow">STATEMENT INTAKE</div><h1>Add a source bill.</h1><p>Upload an electricity PDF in USD and enter its printed values. The statement starts in Needs review. This step does not extract data automatically.</p>
@include('partials.auth-feedback')
<form class="auth-form" method="POST" enctype="multipart/form-data" action="{{ route('customer.bills.store', $organization->id) }}">@csrf
<label for="utility-account">Utility account</label><select id="utility-account" name="utility_account_id" required><option value="">Choose an account</option>@foreach($accounts as $account)<option value="{{ $account->id }}" @selected(old('utility_account_id') == $account->id)>{{ $account->reference }} · {{ $account->supplier }}</option>@endforeach</select>
<label for="document">Source PDF</label><input id="document" type="file" name="document" accept="application/pdf" required><small>PDF, up to 8 MiB. Select the file again after a validation error.</small>
@foreach(['invoice_number' => 'Invoice number', 'period' => 'Reporting month (YYYY-MM-01)', 'issued_on' => 'Issue date', 'service_start' => 'Service start', 'service_end' => 'Service end', 'due_on' => 'Due date', 'current_charges' => 'Current charges (USD)', 'balance_forward' => 'Balance forward / credit (USD)', 'amount_due' => 'Amount due (USD)', 'usage_kwh' => 'Usage (kWh)'] as $field => $label)
<label for="{{ $field }}">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" type="{{ in_array($field, ['issued_on','service_start','service_end','due_on']) ? 'date' : 'text' }}" value="{{ old($field) }}" required maxlength="150">
@endforeach
<small>Use two decimal places for USD, no commas or dollar signs. Enter credits as negative values. Reporting month groups bills; service dates remain separate. Only one statement per utility account/month is supported for now.</small>
<button class="primary-button" @disabled($accounts->isEmpty())>Upload for review</button></form></main></x-customer-layout>
