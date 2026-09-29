<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerStatus
{
    public function change(User $actor, int $organization, int $version, string $action, string $reason): void
    {
        $reason = trim($reason);
        Validator::make(compact('action', 'reason'), ['action' => ['required', 'in:suspend,restore'], 'reason' => ['required', 'string', 'min:10', 'max:2000']])->validate();
        DB::transaction(function () use ($actor, $organization, $version, $action, $reason) {
            Gate::forUser($actor)->authorize('manage-customers');
            $record = DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            abort_unless($record, 404);
            $active = $action === 'restore';
            if ($record->status_version !== $version || (bool) $record->is_active === $active) {
                throw ValidationException::withMessages(['action' => 'This customer status has changed. Reload the page before trying again.']);
            }
            DB::table('organizations')->where('id', $organization)->update(['is_active' => $active, 'status_version' => $version + 1, 'updated_at' => now()]);
            DB::table('organization_status_changes')->insert([
                'organization_id' => $organization, 'actor_id' => $actor->id, 'actor_name' => $actor->fresh()->name,
                'was_active' => $record->is_active, 'is_active' => $active, 'version' => $version + 1, 'reason' => $reason, 'created_at' => now(),
            ]);
        });
    }
}
