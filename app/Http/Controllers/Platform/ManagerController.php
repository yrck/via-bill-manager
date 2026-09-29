<?php

namespace App\Http\Controllers\Platform;

use App\Access\ManagerAccess;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ManagerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:150']]);
        $search = trim($data['search'] ?? '');
        $users = User::query()->when($search !== '', fn ($query) => $query->where(fn ($query) => $query->whereLike('name', '%'.$search.'%')->orWhereLike('email', '%'.$search.'%')))->orderByDesc('is_platform_staff')->orderBy('email')->paginate(20)->withQueryString();

        return view('platform.managers', compact('users', 'search'));
    }

    public function update(Request $request, int $user)
    {
        $data = $request->validate(['manager' => ['required', 'boolean'], 'previous' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $user, $data) {
            Gate::authorize('manage-customers');
            $target = User::whereKey($user)->lockForUpdate()->firstOrFail();
            if ($target->id === $request->user()->id) {
                throw ValidationException::withMessages(['manager' => 'You cannot change your own manager access.']);
            }
            if ($target->is_platform_staff !== (bool) $data['previous']) {
                throw ValidationException::withMessages(['manager' => 'Access changed since this page loaded. Reload before trying again.']);
            }
            if ($data['manager'] && ! ManagerAccess::eligible($target)) {
                throw ValidationException::withMessages(['manager' => 'Managers must have an active account and verified nu-devco.com email address.']);
            }
            if ($target->is_platform_staff === (bool) $data['manager']) {
                return;
            }
            $target->is_platform_staff = (bool) $data['manager'];
            $target->save();
            DB::table('manager_access_changes')->insert(['actor_id' => $request->user()->id, 'user_id' => $target->id, 'is_manager' => $target->is_platform_staff, 'created_at' => now()]);
        });

        return back()->with('status', 'Manager access updated.');
    }
}
