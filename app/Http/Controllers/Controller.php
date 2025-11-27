<?php

namespace App\Http\Controllers;

abstract class Controller
{
    /**
     * Safely check a role on the current user (works even if analyzer cannot infer HasRoles).
     *
     * @param \Illuminate\Contracts\Auth\Authenticatable|null $user
     */
    protected function userHasRole($user, string $role): bool
    {
        if (!$user || !is_object($user) || !method_exists($user, 'hasRole')) {
            return false;
        }

        return (bool) call_user_func([$user, 'hasRole'], $role);
    }
}
