<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TeamInvitations;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function show(Request $request, string $token, TeamInvitations $invitations)
    {
        $invitation = $invitations->find($token);
        if (! $request->user() || ! $request->user()->hasVerifiedEmail()) {
            $request->session()->put('pending_invitation', $token);
        }

        return response()->view('customer.invitation', [
            'invitation' => $invitation, 'token' => $token,
            'organization' => DB::table('organizations')->where('id', $invitation->organization_id)->first(),
        ])->header('Referrer-Policy', 'no-referrer')->header('Cache-Control', 'no-store');
    }

    public function register(Request $request, string $token, TeamInvitations $invitations)
    {
        $invitation = $invitations->find($token);
        $data = $request->validate(['name' => ['required', 'string', 'max:150'], 'password' => ['required', 'confirmed', 'max:72', Password::min(12)]]);
        if (User::where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages(['email' => 'An account already exists for the invited address. Sign in to accept.']);
        }
        $user = new User(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password']]);
        $user->is_active = true;
        $user->save();
        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('pending_invitation', $token);
        event(new Registered($user));

        return redirect()->route('verification.notice');
    }

    public function accept(Request $request, string $token, TeamInvitations $invitations)
    {
        $organization = $invitations->accept($request->user(), $token);
        $request->session()->forget('pending_invitation');

        return redirect()->route('customer.workspace', $organization)->with('status', 'You have joined the organization.');
    }
}
