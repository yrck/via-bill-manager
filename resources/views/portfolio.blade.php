<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Portfolio · VIA</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head>
<body>@include('partials.workspace-nav')<main>
<div class="heading"><div><div class="eyebrow">THE FOUNDATION OF BILL COVERAGE</div><h1>Your portfolio.</h1><p>{{ $accounts->pluck('property')->unique()->count() }} fictional locations · {{ $accounts->count() }} utility accounts</p></div></div>
<p class="portfolio-note">Each utility account has an expected September statement. Addresses, meters and billing schedules will be refined with your sample locations and bills.</p>
@forelse($accounts->groupBy('property') as $property => $group)
<section class="panel"><div class="panel-heading"><div><h2>{{ $property }}</h2><p>{{ $group->count() }} accounts · Fictional location</p></div></div>
<div class="table-scroll"><table class="account-table"><caption class="sr-only">Utility accounts at {{ $property }}</caption><thead><tr><th scope="col">Account</th><th scope="col">Service</th><th scope="col">Supplier</th><th scope="col">Billing period</th></tr></thead><tbody>
@foreach($group as $account)<tr><th scope="row">{{ $account->reference }}</th><td>{{ $account->commodity }}</td><td>{{ $account->supplier }}</td><td>September 2026</td></tr>@endforeach
</tbody></table></div></section>
@empty<section class="panel empty">No fictional accounts have been seeded yet.</section>@endforelse
<footer>VIA Bill Management <span>Local demo · Fictional data only</span></footer></main></body></html>
