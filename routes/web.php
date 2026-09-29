<?php

use App\Demo\BillingDataset;
use App\Http\Middleware\RequireLocalDemo;
use App\Livewire\BillDetail;
use App\Livewire\Bills;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::middleware(RequireLocalDemo::class)->group(function () {
    Route::get('/', Dashboard::class)->name('dashboard');

    Route::get('/portfolio', function () {
        return view('portfolio', ['accounts' => app(BillingDataset::class)->accounts()]);
    })->name('portfolio');

    Route::get('/bills', Bills::class)->name('bills');
    Route::get('/bills/{expectedBill}', BillDetail::class)->whereNumber('expectedBill')->name('bills.show');

});

require __DIR__.'/customer.php';
