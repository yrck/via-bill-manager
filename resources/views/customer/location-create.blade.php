<x-customer-layout><main class="auth-page">
<a class="back-link" href="{{ route('customer.workspace', $organization->id) }}">← {{ $organization->name }}</a>
<div class="eyebrow">BUILD YOUR PORTFOLIO</div><h1>Add your location.</h1><p>Start with a name your team recognizes. Add the utility accounts billed at this location next.</p>
@include('partials.auth-feedback')
<form class="auth-form" method="POST" action="{{ route('customer.locations.store', $organization->id) }}">@csrf
<label for="location-name">Location name</label><input id="location-name" name="name" required maxlength="150" value="{{ old('name') }}" placeholder="e.g. Oak Grove Apartments" aria-describedby="location-help">
<small id="location-help">This location belongs to {{ $organization->name }}. You receive access automatically; teammates receive access only when you assign it.</small>
<button class="primary-button">Create location</button></form>
</main></x-customer-layout>
