<?php

/**
 * Helper akses user yang sedang login.
 */

use App\Entities\User;

if (! function_exists('current_user')) {
    function current_user(): ?User
    {
        return service('auth')->user();
    }
}

if (! function_exists('has_role')) {
    /**
     * Apakah user yang login memiliki salah satu peran.
     */
    function has_role(string ...$roles): bool
    {
        $user = current_user();

        return $user !== null && $user->hasRole(...$roles);
    }
}
