@props(['customerView' => null])
<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>VIA · Customer workspace</title>@vite(['resources/css/app.css', 'resources/js/app.js'])</head><body>
@if($customerView)
<header class="customer-view-banner"><div><strong>Viewing {{ $customerView->user_name }} · {{ $customerView->user_email }}</strong><small>Read-only customer view · Your manager login remains active</small></div><form method="POST" action="{{ route('platform.customer-view.stop') }}">@csrf<button class="inspect">Return to management</button></form></header>
@else
<header class="topbar"><a href="{{ route('customer.home') }}" class="customer-brand">VIA <span>Bill Management</span></a><nav class="workspace-nav" aria-label="Account navigation">@auth @can('manage-customers')<a href="{{ route('platform.home') }}">Customers</a><a href="{{ route('platform.managers') }}">Users & managers</a>@else @if(auth()->user()->is_active && auth()->user()->hasVerifiedEmail())<x-account-switcher />@endif @endcan <form method="POST" action="{{ route('logout') }}">@csrf<button class="text-button">Sign out</button></form>@else<a href="{{ route('login') }}">Sign in</a><a href="{{ route('register') }}">Create account</a>@endauth</nav></header>
@endif
{{ $slot }}
</body></html>
