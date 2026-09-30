<?php

namespace App\Billing;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StatementCharges
{
    public const CATEGORIES = ['consumption' => 'Consumption', 'demand' => 'Demand', 'fixed' => 'Fixed charge', 'tax' => 'Tax', 'late_fee' => 'Late fee', 'deposit' => 'Deposit', 'adjustment' => 'Adjustment', 'other' => 'Other / unclassified'];

    public static function cents(string $value): int
    {
        [$whole, $fraction] = explode('.', ltrim($value, '-'));

        return ((int) $whole * 100 + (int) $fraction) * (str_starts_with($value, '-') ? -1 : 1);
    }

    public function validate(array $input, int $charges): array
    {
        $data = Validator::make($input, [
            'line_items' => ['sometimes', 'array', 'max:100'],
            'line_items.*' => ['array'],
            'line_items.*.description' => ['required', 'string', 'max:255'],
            'line_items.*.category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'line_items.*.quantity' => ['nullable', 'string', 'regex:/^-?\d{1,12}(\.\d{1,6})?$/'],
            'line_items.*.unit' => ['nullable', 'string', 'max:40'],
            'line_items.*.rate' => ['nullable', 'string', 'regex:/^-?\d{1,10}(\.\d{1,8})?$/'],
            'line_items.*.rate_unit' => ['nullable', 'string', 'max:40'],
            'line_items.*.amount' => ['required', 'string', 'regex:/^-?\d{1,9}\.\d{2}$/'],
            'line_items.*.source_reference' => ['nullable', 'string', 'max:255'],
        ])->validate();
        $items = [];
        foreach ($data['line_items'] ?? [] as $row) {
            $items[] = [
                'position' => count($items) + 1, 'description' => $row['description'], 'category' => $row['category'],
                'quantity' => $row['quantity'] ?? null, 'unit' => $row['unit'] ?? null,
                'rate' => $row['rate'] ?? null, 'rate_unit' => $row['rate_unit'] ?? null,
                'amount_cents' => self::cents($row['amount']), 'source_reference' => $row['source_reference'] ?? null,
            ];
        }
        if ($items && array_sum(array_column($items, 'amount_cents')) !== $charges) {
            throw ValidationException::withMessages(['line_items' => 'Detailed charges must total the current charges, excluding balance forward.']);
        }

        return $items;
    }
}
