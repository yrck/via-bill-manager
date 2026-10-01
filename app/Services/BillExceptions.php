<?php

namespace App\Services;

use App\Access\BillingAccess;
use App\Access\CustomerAccounts;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BillExceptions
{
    public function assignees(int $organization, int $bill)
    {
        return DB::table('users as u')
            ->join('organization_memberships as m', 'm.user_id', '=', 'u.id')
            ->join('location_grants as g', 'g.organization_membership_id', '=', 'm.id')
            ->join('utility_accounts as a', 'a.location_id', '=', 'g.location_id')
            ->join('expected_bills as e', 'e.utility_account_id', '=', 'a.id')
            ->where('e.id', $bill)->where('m.organization_id', $organization)
            ->where('m.is_active', true)->where('m.role', 'reviewer')
            ->where('u.is_active', true)->whereNotNull('u.email_verified_at')
            ->select('u.id', 'u.name')->distinct()->orderBy('u.name')->get();
    }

    public function record(User $actor, int $organization, int $bill, array $input): void
    {
        DB::transaction(function () use ($actor, $organization, $bill, $input) {
            // Use the same organization lock as membership changes, intake and reviews.
            DB::table('organizations')->where('id', $organization)->lockForUpdate()->first();
            abort_unless(app(CustomerAccounts::class)->accessible($actor)->where('o.id', $organization)->exists(), 404);
            abort_unless(app(BillingAccess::class)->expectedBills($actor, $organization, true)->where('id', $bill)->exists(), 404);
            $data = Validator::make($input, [
                'version' => ['required', 'integer', 'min:0'],
                'source_revision' => ['required', 'integer', 'min:0'],
                'action' => ['required', 'in:open,update,resolve,reopen'],
                'note' => ['required', 'string', 'min:10', 'max:2000'],
                'assignee_id' => ['nullable', 'integer'],
                'next_action' => ['required_unless:action,resolve', 'nullable', 'string', 'max:500'],
                'due_on' => ['required_unless:action,resolve', 'nullable', 'date_format:Y-m-d'],
            ])->validate();
            $existing = DB::table('bill_exceptions')->where('expected_bill_id', $bill)->first();
            $revision = (int) DB::table('statements')->where('expected_bill_id', $bill)->value('revision_number');
            if ((int) $data['version'] !== (int) ($existing->version ?? 0) || (int) $data['source_revision'] !== $revision) {
                throw ValidationException::withMessages(['exception' => 'The investigation or statement changed. Reload before saving.']);
            }
            $allowed = ! $existing ? ['open'] : ($existing->status === 'open' ? ['update', 'resolve'] : ['reopen']);
            if (! in_array($data['action'], $allowed)) {
                throw ValidationException::withMessages(['exception' => 'This action is unavailable for the current investigation state.']);
            }
            $state = $existing ? $this->snapshot($existing) : [];
            if ($data['action'] !== 'resolve') {
                $assignee = $this->assignees($organization, $bill)->firstWhere('id', $data['assignee_id'] ?? null);
                if (! empty($data['assignee_id']) && ! $assignee) {
                    throw ValidationException::withMessages(['assignee_id' => 'Choose an active, verified reviewer with access to this property.']);
                }
                $state = array_merge($state, [
                    'assignee_id' => $assignee?->id, 'assignee_name' => $assignee?->name,
                    'next_action' => $data['next_action'], 'due_on' => $data['due_on'],
                ]);
            }
            $state = array_merge($state, ['status' => $data['action'] === 'resolve' ? 'resolved' : 'open', 'source_revision' => $revision, 'version' => ($existing->version ?? 0) + 1]);
            $id = $existing?->id;
            if ($id) {
                DB::table('bill_exceptions')->where('id', $id)->update($state + ['updated_at' => now()]);
            } else {
                $id = DB::table('bill_exceptions')->insertGetId($state + ['expected_bill_id' => $bill, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('bill_exception_events')->insert([
                'bill_exception_id' => $id, 'version' => $state['version'], 'action' => $data['action'],
                'actor_id' => $actor->id, 'actor_name' => $actor->name, 'note' => $data['note'],
                'before_state' => $existing ? json_encode($this->snapshot($existing), JSON_THROW_ON_ERROR) : null,
                'after_state' => json_encode($state, JSON_THROW_ON_ERROR), 'created_at' => now(),
            ]);
        });
    }

    private function snapshot(object $record): array
    {
        return array_intersect_key((array) $record, array_flip(['status', 'assignee_id', 'assignee_name', 'next_action', 'due_on', 'source_revision', 'version']));
    }
}
