<?php

namespace App\Console\Commands;

use App\Access\ManagerAccess;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlatformStaff extends Command
{
    protected $signature = 'platform:staff {email : Existing account email} {--revoke : Remove platform access}';

    protected $description = 'Explicitly grant or revoke platform customer-management access for an existing user';

    public function handle(): int
    {
        $user = User::where('email', Str::lower(trim($this->argument('email'))))->first();
        if (! $user || (! $this->option('revoke') && ! ManagerAccess::eligible($user))) {
            $this->error('Granting requires an existing active user with a verified nu-devco.com address. Revocation requires an existing user.');

            return self::FAILURE;
        }
        $user->is_platform_staff = ! $this->option('revoke');
        $user->save();
        Log::notice('Platform staff access changed via trusted console.', ['user_id' => $user->id, 'enabled' => $user->is_platform_staff]);
        $this->info($user->is_platform_staff ? 'Platform access granted.' : 'Platform access revoked.');

        return self::SUCCESS;
    }
}
