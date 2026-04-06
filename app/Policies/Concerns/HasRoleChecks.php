<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait HasRoleChecks
{
    protected function isAdmin(User $user): bool
    {
        return $user->hasRole('Admin');
    }

    protected function isOperator(User $user): bool
    {
        return $user->hasRole('Operator');
    }

    protected function isAdminOrOperator(User $user): bool
    {
        return $this->isAdmin($user) || $this->isOperator($user);
    }
}
