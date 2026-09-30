<x-customer-layout><main>
<a class="back-link" href="{{ route('customer.workspace', $organization->id) }}">← {{ $organization->name }}</a>
<div class="heading"><div><div class="eyebrow">LOCATION</div><h1>{{ $location->name }}</h1><p>Utility accounts and providers for this location.</p></div>@if($organization->owner_user_id === auth()->id())<a class="inspect" href="{{ route('team.index', $organization->id) }}">Manage property access →</a>@endif</div>
@include('partials.auth-feedback')
@if($organization->is_test_account)<div class="review-notice">Test account · Private development data</div>@endif
@if($location->service_address)<p class="portfolio-note">Service location: {{ $location->service_address }}</p>@endif
@if($servicePoints->isNotEmpty())<section class="panel"><div class="panel-heading"><div><h2>Service points & meters</h2><p>Identifiers from supplied statements. No live meter connection is enabled.</p></div></div><div class="table-scroll"><table class="account-table"><thead><tr><th>Service point</th><th>ESI ID</th><th>Meter number</th><th>Source</th></tr></thead><tbody>
@foreach($servicePoints as $point)<tr><td>{{ $point->label }}</td><td>{{ $point->esi_id }}</td><td>@foreach($meters->get($point->id, collect()) as $meter)<div>{{ $meter->meter_number }}<small> · Observed {{ $meter->observed_on }}</small></div>@endforeach</td><td>{{ $point->source_document }}</td></tr>@endforeach
</tbody></table></div></section>@endif
<div class="bill-detail-grid"><section class="panel"><div class="panel-heading"><div><h2>Utility accounts <span class="count">{{ $accounts->total() }}</span></h2><p>Separate accounts for each bill you receive.</p></div></div>
@forelse($accounts as $account)<div class="property-row"><div><strong>{{ $account->supplier }}</strong><small>{{ $account->commodity }}</small></div><div><span>{{ $account->reference }}</span><br><a class="inspect" href="{{ route('customer.accounts.schedule', [$organization->id, $account->id]) }}">Billing schedule →</a></div></div>@empty<div class="empty"><h3>No utility accounts yet.</h3><p>The account owner can add an account number and provider from a bill.</p></div>@endforelse
<div class="pagination">{{ $accounts->links() }}</div></section>
@if($organization->owner_user_id === auth()->id())
<aside class="panel review-form"><h2>Add a utility account</h2><p>Use the account number printed on the bill. Meter and service-point identifiers will be managed separately when integrations are added.</p>
<form class="invite-form" method="POST" action="{{ route('customer.locations.accounts.store', [$organization->id, $location->id]) }}">@csrf
<label for="utility-reference">Utility account number</label><input class="setup-input" id="utility-reference" name="reference" required maxlength="150" value="{{ old('reference') }}" autocomplete="off"><small>Letters, spaces and leading zeroes are preserved.</small>
<label for="utility-supplier">Provider name</label><input class="setup-input" id="utility-supplier" name="supplier" required maxlength="150" value="{{ old('supplier') }}" placeholder="Name shown on your bill">
<label for="utility-commodity">Utility type</label><select id="utility-commodity" name="commodity" required><option value="">Choose utility type</option>@foreach($commodities as $commodity)<option value="{{ $commodity }}" @selected(old('commodity') === $commodity)>{{ $commodity }}</option>@endforeach</select>
<button class="primary-button">Add utility account</button></form></aside>
@else<aside class="panel review-form"><h2>Property access</h2><p>You can view utility accounts at this location. Contact your account owner to add an account or change your access.</p></aside>@endif
</div></main></x-customer-layout>
