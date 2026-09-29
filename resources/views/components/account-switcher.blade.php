<details class="account-switcher">
    <summary aria-label="Switch account{{ $current ? ': '.$current->name : '' }}">
        <span class="account-monogram" aria-hidden="true">{{ $current ? mb_strtoupper(mb_substr($current->name, 0, 1)) : 'V' }}</span>
        <span class="account-switcher-label"><small>{{ $current ? 'CURRENT ACCOUNT' : 'YOUR ACCOUNTS' }}</small><strong>{{ $current?->name ?? 'Choose an account' }}</strong></span>
        <span class="account-chevron" aria-hidden="true">⌄</span>
    </summary>
    <div class="account-switcher-menu">
        <div class="account-switcher-heading">Switch account <span>{{ $accounts->count() }}</span></div>
        <nav aria-label="Customer accounts" class="account-options">
            @forelse($accounts as $account)
                <a href="{{ route('customer.workspace', $account->id) }}" @if($current?->id === $account->id) aria-current="page" @endif>
                    <span class="account-monogram" aria-hidden="true">{{ mb_strtoupper(mb_substr($account->name, 0, 1)) }}</span>
                    <span class="account-option-label"><strong>{{ $account->name }}</strong><small>{{ $account->owner_user_id === auth()->id() ? 'Owner' : ucfirst($account->role) }}</small></span>
                    @if($current?->id === $account->id)<span class="account-selected">✓<span class="sr-only"> Current account</span></span>@endif
                </a>
            @empty
                <p class="account-switcher-empty">No active accounts. Ask your account owner for an invitation.</p>
            @endforelse
        </nav>
        <a class="account-switcher-footer" href="{{ route('customer.home') }}">View all accounts <span aria-hidden="true">→</span></a>
    </div>
</details>
