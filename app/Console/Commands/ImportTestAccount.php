<?php

namespace App\Console\Commands;

use App\Services\TestAccountImport;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class ImportTestAccount extends Command
{
    protected $signature = 'account:import-test {file : Private JSON manifest, or - for standard input} {--owner= : Owner email} {--create-owner : Create a missing login with an unknown random password; password setup and email verification remain required} {--allow-development-server : Explicitly permit this import on a development server running production mode}';

    protected $description = 'Provision a private test account and meter inventory without importing bills or changing existing access';

    public function handle(TestAccountImport $import): int
    {
        if (! app()->environment('local', 'testing') && ! $this->option('allow-development-server')) {
            $this->error('Outside local/testing, explicitly specify --allow-development-server for the intended development server.');

            return self::FAILURE;
        }
        if (! $this->option('owner')) {
            $this->error('Specify --owner with the intended owner login email.');

            return self::FAILURE;
        }
        $path = $this->argument('file');
        if ($path !== '-' && (! is_file($path) || ! is_readable($path))) {
            $this->error('Private manifest is not readable.');

            return self::FAILURE;
        }
        $raw = file_get_contents($path === '-' ? 'php://stdin' : $path, false, null, 0, 1048577);
        try {
            if ($raw === false || strlen($raw) > 1048576) {
                throw new \RuntimeException('Invalid size.');
            }
            $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \RuntimeException('Invalid manifest.');
            }
        } catch (\Throwable) {
            $this->error('Provide a valid JSON object no larger than 1 MiB.');

            return self::FAILURE;
        }
        try {
            $id = $import->run($payload, $this->option('owner'), (bool) $this->option('create-owner'));
        } catch (ValidationException $error) {
            foreach ($error->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->info('Test dataset ready at /workspace/'.$id.'. Existing imports are left unchanged.');

        return self::SUCCESS;
    }
}
