<?php

namespace App\Policies;

use App\Models\BirdType;
use App\Models\User;
use App\Policies\Concerns\HasRoleChecks;

class BirdTypePolicy
{
    use HasRoleChecks;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, BirdType $birdType): bool
    {
        return $this->isAdminOrOperator($user);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, BirdType $birdType): bool
    {
        return $this->isAdminOrOperator($user);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, BirdType $birdType): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, BirdType $birdType): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, BirdType $birdType): bool
    {
        return false;
    }
}
