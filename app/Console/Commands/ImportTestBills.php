<?php

namespace App\Console\Commands;

use App\Services\BillIntake;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportTestBills extends Command
{
    protected $signature = 'account:import-test-bills {dataset : Existing private test dataset key} {manifest : Private JSON manifest with account references and local PDF paths} {--allow-development-server}';

    protected $description = 'Import manually transcribed source bills into an existing private test account';

    public function handle(BillIntake $intake): int
    {
        if (! app()->environment('local', 'testing') && ! $this->option('allow-development-server')) {
            $this->error('Explicit --allow-development-server is required outside local/testing.');

            return self::FAILURE;
        }
        $import = DB::table('test_account_imports')->where('dataset_key', $this->argument('dataset'))->first();
        if (! $import || ! is_file($this->argument('manifest'))) {
            $this->error('A provisioned test dataset and readable manifest are required.');

            return self::FAILURE;
        }
        try {
            $rows = json_decode(file_get_contents($this->argument('manifest')), true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 100) {
                throw new \RuntimeException('Invalid list.');
            }
            foreach ($rows as $row) {
                $account = DB::table('utility_accounts as a')->join('locations as l', 'l.id', '=', 'a.location_id')->join('portfolios as p', 'p.id', '=', 'l.portfolio_id')->where('p.organization_id', $import->organization_id)->where('a.reference', $row['reference'])->get(['a.id']);
                if ($account->count() !== 1) {
                    throw new \RuntimeException('Utility account reference must resolve exactly once.');
                }
                $row['utility_account_id'] = $account->first()->id;
                $id = $intake->record($import->organization_id, $row, $row['file'], basename($row['file']), null);
                $this->info('Imported bill '.$id.' for review.');
            }
        } catch (\Throwable $error) {
            // Partial batches are explicit: prior successful rows stay imported; duplicates never overwrite.
            $this->error('Import stopped: '.$error->getMessage().' Earlier successful rows remain imported.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
