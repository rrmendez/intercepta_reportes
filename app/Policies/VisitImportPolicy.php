<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitImport;
use App\Policies\Concerns\HasRoleChecks;

class VisitImportPolicy
{
    use HasRoleChecks;

    public function viewAny(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function view(User $user, VisitImport $visitImport): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, VisitImport $visitImport): bool
    {
        return false;
    }

    public function delete(User $user, VisitImport $visitImport): bool
    {
        return false;
    }

    public function restore(User $user, VisitImport $visitImport): bool
    {
        return false;
    }

    public function forceDelete(User $user, VisitImport $visitImport): bool
    {
        return false;
    }
}
