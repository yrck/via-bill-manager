<section class="panel" id="validation"><div class="panel-heading"><h2>Validation evidence</h2></div><div class="detail-body"><p>Screening checks identify potential issues. A clear result is not a full audit or bill verification. Service days include both printed dates; shared boundary dates may need investigation.</p>
@if($canReview)<form method="POST" action="{{ route('customer.bills.validate', [$organization->id, $bill->id]) }}">@csrf<button class="inspect">Refresh validation</button></form>@endif
@if(!$historical && !$bill->validation_run_id)<p>Not evaluated. A reviewer can refresh validation for existing statements. New intake and corrections run checks automatically.</p>@endif
@if(!$historical)<p><a class="inspect" href="#investigation">Investigate a finding / review follow-up →</a></p>@endif
</div>
@foreach($validationRuns as $run)
@php($inputs = json_decode($run->inputs, true))
@php($findings = json_decode($run->findings, true))
<article class="review-event"><div><strong>Evaluation #{{ $run->id }} · {{ $run->id === $bill->validation_run_id && !$historical ? 'Current' : 'Retained history' }}</strong><time>{{ \Carbon\CarbonImmutable::parse($run->created_at)->utc()->format('M j, Y H:i') }} UTC</time></div><p>Rules: {{ $run->rules_version }} · Source version {{ $run->source_revision }}</p>
<p>Source: {{ $inputs['current']['service_start'] ?? 'Unknown start' }} → {{ $inputs['current']['service_end'] ?? 'Unknown end' }} · {{ $inputs['current']['usage_kwh'] ?? 'Unknown' }} kWh · {{ $inputs['current']['currency'] }} {{ number_format($inputs['current']['charges_cents'] / 100, 2) }} current charges.</p>
@if($inputs['previous'])
<p>Comparison: <a class="inspect" href="{{ $customerView ? route('platform.customer-view.bill', ['bill' => $inputs['previous']['expected_bill_id'], 'revision' => $inputs['previous']['revision_number']]) : route('customer.bills.show', ['organization' => $organization->id, 'bill' => $inputs['previous']['expected_bill_id'], 'revision' => $inputs['previous']['revision_number']]) }}">{{ \Carbon\CarbonImmutable::parse($inputs['previous']['period'])->format('M Y') }} · Version {{ $inputs['previous']['revision_number'] }}</a><br>{{ $inputs['previous']['service_start'] ?? 'Unknown start' }} → {{ $inputs['previous']['service_end'] ?? 'Unknown end' }} · {{ $inputs['previous']['usage_kwh'] ?? 'Unknown' }} kWh · {{ $inputs['previous']['currency'] }} {{ number_format($inputs['previous']['charges_cents'] / 100, 2) }} current charges.</p>
@else<p>No earlier statement is available for comparison.</p>@endif
@foreach($findings as $finding)<div class="revision-note"><strong>{{ $finding['rule'] }} · {{ ['warning' => 'Needs investigation', 'clear' => 'No issue flagged', 'insufficient' => 'Insufficient data / history'][$finding['status']] }}</strong><p>{{ $finding['explanation'] }}</p></div>@endforeach
</article>
@endforeach
<div class="pagination">{{ $validationRuns->links() }}</div></section>
