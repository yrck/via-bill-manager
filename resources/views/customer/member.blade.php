<x-customer-layout>
<main>
    <a class="back-link" href="{{ route('team.index', $organization->id) }}">← {{ $organization->name }} · Team</a>
    <div class="heading"><div><div class="eyebrow">MEMBER ACCESS</div><h1>{{ $member->name }}</h1><p>{{ $member->email }} · {{ $organization->name }}</p></div><span class="status-badge">{{ $member->is_active ? 'Active member' : 'Inactive member' }}</span></div>
    @include('partials.auth-feedback')
    <div class="bill-detail-grid">
        <section class="panel review-form">
            <h2>Access to this account</h2><p>Changes apply to this account only. Other account memberships and this person's login are unaffected.</p>
            <form method="POST" action="{{ route('team.member.update', [$organization->id, $member->id]) }}">
                @csrf @method('PUT')
                <input type="hidden" name="version" value="{{ old('version', $member->access_version) }}">
                <label for="member-role">Role</label>
                <select id="member-role" name="role"><option value="viewer" @selected(old('role', $member->role) === 'viewer')>Viewer — read only</option><option value="reviewer" @selected(old('role', $member->role) === 'reviewer')>Reviewer — review bills</option></select>
                <label for="member-status">Membership</label>
                <select id="member-status" name="is_active" aria-describedby="status-help"><option value="1" @selected((string) old('is_active', (int) $member->is_active) === '1')>Active</option><option value="0" @selected((string) old('is_active', (int) $member->is_active) === '0')>Inactive — remove account access</option></select>
                <small id="status-help">Deactivation removes all property grants. To restore access later, select Active and choose properties again.</small>
                <fieldset class="property-grants"><legend>Property access</legend>
                    @forelse($locations as $location)<label><input type="checkbox" name="locations[]" value="{{ $location->id }}" @checked(in_array($location->id, session()->hasOldInput('version') ? old('locations', []) : $grants))> {{ $location->name }}</label>@empty<p>No properties available to assign yet.</p>@endforelse
                </fieldset>
                <small>Saving replaces the member's property access with this selection. No selection means no bill access. You can assign only properties you can access.</small>
                <label for="access-reason" class="access-reason-label">Reason for this change</label><textarea id="access-reason" name="reason" required minlength="10" maxlength="1000" rows="3" placeholder="e.g. Responsible for reviewing the west region's bills">{{ old('reason') }}</textarea>
                <button class="primary-button">Save member access</button>
            </form>
        </section>
        <aside class="panel">
            <div class="panel-heading"><div><h2>Access history</h2><p>Who changed access, when, and why.</p></div></div>
            @forelse($history as $event)
                @php($before = json_decode($event->before, true))
                @php($after = json_decode($event->after, true))
                <article class="review-event"><div><strong>{{ $event->actor_name }}</strong><time>{{ \Carbon\Carbon::parse($event->created_at)->format('M j, Y H:i') }} UTC</time></div><p>{{ ucfirst($before['role']) }} · {{ $before['is_active'] ? 'Active' : 'Inactive' }} → {{ ucfirst($after['role']) }} · {{ $after['is_active'] ? 'Active' : 'Inactive' }}</p><p>Properties: {{ implode(', ', $before['location_names']) ?: 'None' }} → {{ implode(', ', $after['location_names']) ?: 'None' }}</p><p>{{ $event->reason }}</p></article>
            @empty<div class="empty">No access changes recorded yet.</div>@endforelse
            <div class="pagination">{{ $history->links() }}</div>
        </aside>
    </div>
</main>
</x-customer-layout>
