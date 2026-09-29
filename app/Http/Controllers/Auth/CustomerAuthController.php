<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'organization' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]);
        $user = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            $user->is_active = true;
            $user->save();
            $organization = DB::table('organizations')->insertGetId([
                'name' => $data['organization'], 'key' => (string) Str::uuid(),
                'owner_user_id' => $user->id, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('organization_memberships')->insert([
                'organization_id' => $organization, 'user_id' => $user->id, 'role' => 'reviewer',
                'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $user;
        });
        Auth::login($user);
        $request->session()->regenerate();
        event(new Registered($user));

        return redirect()->route('verification.notice');
    }

    public function login(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:254'], 'password' => ['required', 'string']]);
        $key = 'customer-login:'.hash('sha256', $data['email'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Please try again in a minute.']);
        }
        if (! Auth::attempt([...$data, 'is_active' => true])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'These credentials could not be verified.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->route(Gate::allows('manage-customers') ? 'platform.home' : 'customer.home');
    }

    public function logout(Request $request)
    {
        if ($id = $request->session()->pull('customer_view_id')) {
            DB::table('customer_view_sessions')->where('id', $id)->where('manager_id', $request->user()->id)->whereNull('ended_at')->update(['ended_at' => now()]);
        }
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function forgotPassword(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $request->validate(['email' => ['required', 'email', 'max:254']]);
        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If an account matches that email, a password reset link has been sent.');
    }

    public function resetPassword(Request $request)
    {
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This reset link is invalid or expired. Request a new one.']);
        }

        return redirect()->route('login')->with('status', 'Your password has been reset. Sign in with your new password.');
    }
}
