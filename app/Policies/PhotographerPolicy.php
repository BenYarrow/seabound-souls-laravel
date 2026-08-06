<?php

// Photography credits are the owner's editorial responsibility — contributors
// author spot guides, they never manage photographers.

namespace App\Policies;

use App\Models\Photographer;
use App\Models\User;

class PhotographerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }
}
