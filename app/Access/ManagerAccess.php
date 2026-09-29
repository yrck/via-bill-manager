<?php

namespace App\Access;

use App\Models\User;

class ManagerAccess
{
    public static function eligible(User $user): bool
    {
        return $user->is_active && $user->hasVerifiedEmail()
            && strtolower(substr(strrchr($user->email, '@') ?: '', 1)) === 'nu-devco.com';
    }

    public static function allowed(User $user): bool
    {
        $current = $user->fresh();

        return $current && $current->is_platform_staff && self::eligible($current);
    }
}
