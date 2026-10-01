<section class="panel" id="investigation"><div class="panel-heading"><h2>Investigation &amp; follow-up</h2><span class="status-badge">{{ $exception ? ucfirst($exception->status) : 'Not opened' }}</span></div>
<div class="detail-body"><p>One investigation thread per billing month and utility account. Use it to coordinate collection or billing concerns. Resolution does not verify the bill, confirm payment or record a recovery.</p>
@if($historical)<p>Investigation details below reflect the current workflow, not this historical statement version.</p>@endif
@if($exception)
<dl class="statement-summary"><div><dt>Assigned to</dt><dd>{{ $exception->assignee_name ?? 'Unassigned' }} @if($exception->assignee_id && !$assignees->contains('id', $exception->assignee_id)) · No longer eligible; reassign this investigation @endif</dd></div><div><dt>Next action</dt><dd>{{ $exception->next_action }}</dd></div><div><dt>Follow-up due</dt><dd>{{ $exception->due_on }} @if($exception->status === 'open' && $exception->due_on < today()->toDateString()) · Overdue @endif</dd></div><div><dt>Last considered source</dt><dd>{{ $exception->source_revision ? 'Statement version '.$exception->source_revision : 'No statement received' }}</dd></div></dl>
@if((int) $exception->source_revision !== (int) $bill->revision_number)<div class="revision-note"><strong>Source statement changed</strong><p>A statement arrived or was corrected after the last investigation action. Review the current version before updating, resolving or reopening this investigation. Its status has not changed automatically.</p></div>@endif
@endif
@if($canManageException)
<form class="auth-form" method="POST" action="{{ route('customer.bills.exception', [$organization->id, $bill->id]) }}">@csrf
<input type="hidden" name="version" value="{{ $exception->version ?? 0 }}"><input type="hidden" name="source_revision" value="{{ $bill->revision_number ?? 0 }}">
<label for="exception-action">Action</label><select id="exception-action" name="action">
@if(!$exception)<option value="open">Open investigation</option>@elseif($exception->status === 'resolved')<option value="reopen">Reopen investigation</option>@else<option value="update">Add note / update follow-up</option><option value="resolve" @selected(old('action') === 'resolve')>Resolve investigation</option>@endif
</select>
<label for="exception-assignee">Assigned reviewer</label><select id="exception-assignee" name="assignee_id"><option value="">Unassigned</option>@foreach($assignees as $assignee)<option value="{{ $assignee->id }}" @selected((string) old('assignee_id', $exception->assignee_id ?? '') === (string) $assignee->id)>{{ $assignee->name }}</option>@endforeach</select>
<label for="exception-next">Next action</label><input id="exception-next" name="next_action" maxlength="500" value="{{ old('next_action', $exception->next_action ?? '') }}">
<label for="exception-due">Follow-up due (UTC)</label><input id="exception-due" type="date" name="due_on" value="{{ old('due_on', $exception->due_on ?? '') }}"><small>Next action and due date are required for open investigations. Resolving preserves the last assignment and follow-up.</small>
<label for="exception-note">Investigation note / resolution evidence</label><textarea id="exception-note" name="note" required minlength="10" maxlength="2000" rows="4">{{ old('note') }}</textarea>
<p>Saving records your assessment against {{ $bill->revision_number ? 'current statement version '.$bill->revision_number : 'the current expectation with no received statement' }}.</p>
<button class="primary-button">Save investigation</button></form>
@elseif(!$exception)<p>No investigation has been recorded. A reviewer with property access can open one from the current bill page.</p>@endif
</div>
@foreach($exceptionEvents as $event)
@php($state = json_decode($event->after_state, true))
<article class="review-event"><div><strong>{{ ['open' => 'Opened', 'update' => 'Updated', 'resolve' => 'Resolved', 'reopen' => 'Reopened'][$event->action] }} · {{ $event->actor_name }}</strong><time>{{ \Carbon\CarbonImmutable::parse($event->created_at)->utc()->format('M j, Y H:i') }} UTC</time></div><p>{{ $event->note }}</p><small>{{ $state['assignee_name'] ?? 'Unassigned' }} · {{ $state['next_action'] }} · Due {{ $state['due_on'] }} · {{ $state['source_revision'] ? 'Source version '.$state['source_revision'] : 'No statement received' }}</small></article>
@endforeach
<div class="pagination">{{ $exceptionEvents->links() }}</div></section>
