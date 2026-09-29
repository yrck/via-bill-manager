<?php

namespace Database\Seeders;

use App\Demo\FictionalStatements;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoPortfolioSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! config('demo.enabled')) {
            throw new RuntimeException('Fictional seeding requires local/testing and DEMO_ENABLED=true.');
        }

        DB::transaction(function () {
            $portfolio = $this->record('portfolios', ['key' => 'fictional-september-2026'], ['name' => 'Example portfolio']);
            foreach (app(FictionalStatements::class)->statements() as $row) {
                $location = $this->record('locations', ['portfolio_id' => $portfolio, 'name' => $row['property']]);
                $account = $this->record('utility_accounts', ['location_id' => $location, 'reference' => $row['account']], ['commodity' => $row['commodity'], 'supplier' => $row['vendor']]);
                $expected = $this->record('expected_bills', ['utility_account_id' => $account, 'period' => '2026-09-01'], ['expected_by' => '2026-09-'.($row['status'] === 'missing' ? substr($row['date'], -2) : '22')]);
                if ($row['status'] !== 'missing') {
                    $this->record('statements', ['expected_bill_id' => $expected], [
                        'charges_cents' => $row['cents'], 'currency' => 'USD', 'received_on' => '2026-09-22',
                        'due_on' => $row['status'] === 'review' ? ['South Campus' => '2026-10-02', 'East Campus' => '2026-10-05', 'West Campus' => '2026-10-12'][$row['property']] : null,
                        'status' => $row['status'], 'title' => $row['title'], 'evidence' => $row['evidence'], 'owner' => $row['owner'],
                    ]);
                }
            }
        });
    }

    // Re-running the seed preserves existing records and subsequent local edits.
    private function record(string $table, array $key, array $values = []): int
    {
        return DB::table($table)->where($key)->value('id') ?? DB::table($table)->insertGetId([...$key, ...$values]);
    }
}
