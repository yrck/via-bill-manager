<?php

namespace App\View\Components;

use App\Access\CustomerAccounts;
use Illuminate\View\Component;

class AccountSwitcher extends Component
{
    public function render()
    {
        $accounts = app(CustomerAccounts::class)->accessible(auth()->user())->orderBy('o.name')->get();
        $current = $accounts->firstWhere('id', (int) request()->route('organization'));

        return view('components.account-switcher', compact('accounts', 'current'));
    }
}
