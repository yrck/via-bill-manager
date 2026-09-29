<x-customer-layout><main class="auth-page"><div class="eyebrow">WELCOME BACK</div><h1>Sign in to VIA.</h1><p>Your bills, properties and team in one workspace.</p>@include('partials.auth-feedback')
<form method="POST" action="{{ route('login') }}" class="auth-form">@csrf
<label for="email">Email address</label><input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email">
<label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password">
<a class="auth-secondary" href="{{ route('password.request') }}">Forgot your password?</a><button class="primary-button">Sign in</button></form><p class="auth-foot">New to VIA? <a href="{{ route('register') }}">Create your workspace</a></p></main></x-customer-layout>
