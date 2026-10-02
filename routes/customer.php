<?php

use App\Http\Controllers\Auth\CustomerAuthController;
use App\Http\Controllers\BillingScheduleController;
use App\Http\Controllers\BillIntakeController;
use App\Http\Controllers\CustomerBillController;
use App\Http\Controllers\CustomerLocationController;
use App\Http\Controllers\CustomerWorkspaceController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\Platform\CustomerController;
use App\Http\Controllers\Platform\CustomerViewController;
use App\Http\Controllers\Platform\ManagerController;
use App\Http\Controllers\TeamAccessController;
use App\Http\Controllers\TeamController;
use App\Http\Middleware\RequireActiveCustomer;
use App\Http\Middleware\RequirePlatformStaff;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [CustomerAuthController::class, 'register'])->middleware('throttle:5,1');
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [CustomerAuthController::class, 'login'])->middleware('throttle:20,1');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [CustomerAuthController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token, Request $request) => view('auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]))->name('password.reset');
    Route::post('/reset-password', [CustomerAuthController::class, 'resetPassword'])->middleware('throttle:5,1')->name('password.update');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [CustomerAuthController::class, 'logout'])->name('logout');
    Route::middleware([RequireActiveCustomer::class, 'auth.session'])->group(function () {
        Route::view('/verify-email', 'auth.verify-email')->name('verification.notice');
        Route::get('/verify-email/{id}/{hash}', function (EmailVerificationRequest $request) {
            $request->fulfill();

            return redirect()->route('customer.home');
        })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
        Route::post('/email/verification-notification', function (Request $request) {
            if (! $request->user()->hasVerifiedEmail()) {
                $request->user()->sendEmailVerificationNotification();
            }

            return back()->with('status', 'A verification link has been sent.');
        })->middleware('throttle:3,1')->name('verification.send');
        Route::middleware('verified')->group(function () {
            Route::get('/workspace', [CustomerWorkspaceController::class, 'index'])->name('customer.home');
            Route::get('/workspace/{organization}', [CustomerWorkspaceController::class, 'show'])->whereNumber('organization')->name('customer.workspace');
        });
    });
});

Route::get('/invitations/{token}', [InvitationController::class, 'show'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:30,1')->name('invitations.show');
Route::post('/invitations/{token}/register', [InvitationController::class, 'register'])->where('token', '[A-Za-z0-9]{64}')->middleware(['guest', 'throttle:5,1'])->name('invitations.register');
Route::middleware(['auth', RequireActiveCustomer::class, 'auth.session', 'verified'])->group(function () {
    Route::get('/workspace/{organization}/accounts/{account}/schedule', [BillingScheduleController::class, 'show'])->whereNumber(['organization', 'account'])->name('customer.accounts.schedule');
    Route::post('/workspace/{organization}/accounts/{account}/schedule', [BillingScheduleController::class, 'store'])->whereNumber(['organization', 'account'])->middleware('throttle:10,1')->name('customer.accounts.schedule.store');
    Route::get('/workspace/{organization}/exceptions', [CustomerBillController::class, 'exceptions'])->whereNumber('organization')->name('customer.exceptions');
    Route::post('/workspace/{organization}/bills/{bill}/validate', [CustomerBillController::class, 'validateBill'])->whereNumber(['organization', 'bill'])->middleware('throttle:10,1')->name('customer.bills.validate');
    Route::post('/workspace/{organization}/bills/{bill}/exception', [CustomerBillController::class, 'exception'])->whereNumber(['organization', 'bill'])->middleware('throttle:20,1')->name('customer.bills.exception');
    Route::get('/workspace/{organization}/bills/upload', [BillIntakeController::class, 'create'])->whereNumber('organization')->name('customer.bills.upload');
    Route::post('/workspace/{organization}/bills', [BillIntakeController::class, 'store'])->whereNumber('organization')->middleware('throttle:10,1')->name('customer.bills.store');
    Route::get('/workspace/{organization}/bills/{bill}/document', [BillIntakeController::class, 'download'])->whereNumber(['organization', 'bill'])->name('customer.bills.document');
    Route::get('/workspace/{organization}/bills/{bill}/correct', [BillIntakeController::class, 'correction'])->whereNumber(['organization', 'bill'])->name('customer.bills.correct');
    Route::post('/workspace/{organization}/bills/{bill}/correct', [BillIntakeController::class, 'correct'])->whereNumber(['organization', 'bill'])->middleware('throttle:10,1')->name('customer.bills.correct.store');
    Route::get('/workspace/{organization}/bills', [CustomerBillController::class, 'index'])->whereNumber('organization')->name('customer.bills');
    Route::get('/workspace/{organization}/bills/{bill}', [CustomerBillController::class, 'show'])->whereNumber(['organization', 'bill'])->name('customer.bills.show');
    Route::post('/workspace/{organization}/bills/{bill}/review', [CustomerBillController::class, 'review'])->whereNumber(['organization', 'bill'])->middleware('throttle:20,1')->name('customer.bills.review');
    Route::get('/workspace/{organization}/locations/create', [CustomerLocationController::class, 'create'])->whereNumber('organization')->name('customer.locations.create');
    Route::post('/workspace/{organization}/locations', [CustomerLocationController::class, 'store'])->whereNumber('organization')->middleware('throttle:20,1')->name('customer.locations.store');
    Route::get('/workspace/{organization}/locations/{location}', [CustomerLocationController::class, 'show'])->whereNumber(['organization', 'location'])->name('customer.locations.show');
    Route::post('/workspace/{organization}/locations/{location}/accounts', [CustomerLocationController::class, 'storeAccount'])->whereNumber(['organization', 'location'])->middleware('throttle:20,1')->name('customer.locations.accounts.store');
    Route::post('/invitations/{token}/accept', [InvitationController::class, 'accept'])->where('token', '[A-Za-z0-9]{64}')->middleware('throttle:10,1')->name('invitations.accept');
    Route::get('/workspace/{organization}/team/members/{membership}', [TeamAccessController::class, 'edit'])->whereNumber(['organization', 'membership'])->name('team.member.edit');
    Route::put('/workspace/{organization}/team/members/{membership}', [TeamAccessController::class, 'update'])->whereNumber(['organization', 'membership'])->middleware('throttle:20,1')->name('team.member.update');
    Route::get('/workspace/{organization}/team', [TeamController::class, 'index'])->whereNumber('organization')->name('team.index');
    Route::post('/workspace/{organization}/team/invite', [TeamController::class, 'invite'])->whereNumber('organization')->middleware('throttle:10,1')->name('team.invite');
    Route::post('/workspace/{organization}/team/invitations/{invitation}/cancel', [TeamController::class, 'cancel'])->whereNumber(['organization', 'invitation'])->name('team.cancel');
});

Route::prefix('management')->middleware(['auth', RequireActiveCustomer::class, 'auth.session', 'verified', RequirePlatformStaff::class])->group(function () {
    Route::get('/', fn () => redirect()->route('platform.customers.index'))->name('platform.home');
    Route::get('/users', [ManagerController::class, 'index'])->name('platform.managers');
    Route::post('/users/{user}/manager', [ManagerController::class, 'update'])->whereNumber('user')->middleware('throttle:20,1')->name('platform.managers.update');
    Route::post('/customers/{organization}/view', [CustomerViewController::class, 'start'])->whereNumber('organization')->name('platform.customer-view.start');
    Route::get('/customer-view', [CustomerViewController::class, 'show'])->name('platform.customer-view');
    Route::get('/customer-view/exceptions', [CustomerViewController::class, 'exceptions'])->name('platform.customer-view.exceptions');
    Route::get('/customer-view/bills', [CustomerViewController::class, 'bills'])->name('platform.customer-view.bills');
    Route::get('/customer-view/bills/{bill}/document', [CustomerViewController::class, 'document'])->whereNumber('bill')->name('platform.customer-view.document');
    Route::get('/customer-view/bills/{bill}', [CustomerViewController::class, 'bill'])->whereNumber('bill')->name('platform.customer-view.bill');
    Route::get('/customer-view/team', [CustomerViewController::class, 'team'])->name('platform.customer-view.team');
    Route::post('/customer-view/stop', [CustomerViewController::class, 'stop'])->name('platform.customer-view.stop');
    Route::get('/customers', [CustomerController::class, 'index'])->name('platform.customers.index');
    Route::get('/customers/{organization}', [CustomerController::class, 'show'])->whereNumber('organization')->name('platform.customers.show');
    Route::post('/customers/{organization}/status', [CustomerController::class, 'update'])->whereNumber('organization')->middleware('throttle:20,1')->name('platform.customers.status');
});
