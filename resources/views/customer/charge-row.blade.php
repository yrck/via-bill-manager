<fieldset class="charge-row" data-charge-row><legend>Charge line</legend>
<div class="intake-fields">
<div><label for="charge-{{ $index }}-description">Printed description</label><input id="charge-{{ $index }}-description" name="line_items[{{ $index }}][description]" value="{{ $row['description'] ?? '' }}" required maxlength="255"></div>
<div><label for="charge-{{ $index }}-category">Category</label><select id="charge-{{ $index }}-category" name="line_items[{{ $index }}][category]" required>@foreach(\App\Billing\StatementCharges::CATEGORIES as $value => $label)<option value="{{ $value }}" @selected(($row['category'] ?? 'other') === $value)>{{ $label }}</option>@endforeach</select></div>
@foreach(['quantity' => 'Quantity', 'unit' => 'Quantity unit (e.g. kWh)', 'rate' => 'Printed rate', 'rate_unit' => 'Rate unit (e.g. USD/kWh)', 'amount' => 'Amount (USD)', 'source_reference' => 'Source reference (e.g. page 2)'] as $field => $label)
<div><label for="charge-{{ $index }}-{{ $field }}">{{ $label }}</label><input id="charge-{{ $index }}-{{ $field }}" name="line_items[{{ $index }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}" @required($field === 'amount') maxlength="{{ $field === 'source_reference' ? 255 : 40 }}"></div>
@endforeach
</div><button type="button" class="text-button" data-remove-charge>Remove charge line</button></fieldset>
