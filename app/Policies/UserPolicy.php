<?php

// Authorises the User model. Only owners may manage user (contributor) accounts via
// the Contributors admin section; contributors have no access to it. Panel *login* is a
// separate concern handled by User::canAccessPanel(), not this policy.

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->isOwner();
    }
}
