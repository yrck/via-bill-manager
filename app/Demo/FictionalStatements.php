<?php

namespace App\Demo;

use Illuminate\Support\Collection;

/** Fictional snapshot. Replace with permission-scoped queries before real data. */
class FictionalStatements
{
    public function statements(): Collection
    {
        return collect(['North Campus', 'South Campus', 'East Campus', 'West Campus'])->flatMap(function (string $property, int $index) {
            return collect(range(1, 6))->map(function (int $number) use ($property, $index) {
                $missing = $index > 0 && $number === 6;
                $review = $index > 0 && $number === 1;
                $titles = [1 => 'Usage increased 31%', 2 => 'Estimated meter reading', 3 => 'Charge total needs checking'];
                $evidence = [1 => '24,100 kWh over 30 days versus 18,400 kWh over the comparable prior period. Check occupancy and operating changes before accepting the variance.', 2 => 'The utility marked consumption as estimated. An actual reading has not yet been linked.', 3 => 'Extracted line items total $1,940; statement new charges total $1,960. The $20 difference needs review.'];

                return [
                    'id' => ($index * 6) + $number, 'property' => $property,
                    'account' => sprintf('DEMO-%d%02d', $index + 1, $number),
                    'commodity' => $number % 2 === 0 ? 'Natural gas' : 'Electric',
                    'vendor' => $number % 2 === 0 ? 'Example Gas' : 'Example Electric',
                    'status' => $missing ? 'missing' : ($review ? 'review' : 'verified'),
                    'title' => $missing ? 'September statement missing' : ($review ? $titles[$index] : 'Statement verified'),
                    'cents' => $missing ? null : ($review ? [1 => 842000, 2 => 318000, 3 => 196000][$index] : ($index === 0 && $number === 1 ? 210000 : 160000)),
                    'due_soon' => $review && $index < 3,
                    'date' => $missing ? 'Expected Sep '.(22 + $index) : ($review ? [1 => 'Due Oct 2', 2 => 'Due Oct 5', 3 => 'Due Oct 12'][$index] : 'Received Sep 22'),
                    'owner' => $missing ? 'Unassigned' : ($index % 2 === 0 ? 'J. Rivera' : 'M. Chen'),
                    'evidence' => $missing ? 'No accepted statement matches the expected September obligation after its receipt date and grace period.' : ($review ? $evidence[$index] : 'Required fields and totals passed the demo validation rules.'),
                    'next' => $missing ? 'Check collection history and locate the expected statement.' : 'Compare the finding with the source statement and document a resolution.',
                ];
            });
        });
    }
}
