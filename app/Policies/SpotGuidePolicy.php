<?php

// Authorises spot-guide access. Owners have full control. Riders are limited to
// their own guides and may only delete them while still unpublished — once live,
// the house owns it (only owners may unpublish/delete).

namespace App\Policies;

use App\Models\SpotGuide;
use App\Models\User;

class SpotGuidePolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list is query-scoped per role
    }

    public function view(User $user, SpotGuide $guide): bool
    {
        return $user->isOwner() || $guide->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SpotGuide $guide): bool
    {
        return $user->isOwner() || $guide->user_id === $user->id;
    }

    public function delete(User $user, SpotGuide $guide): bool
    {
        if ($user->isOwner()) {
            return true;
        }

        // Rider: own guide, and only while it is not yet live.
        return $guide->user_id === $user->id && ! $guide->is_published;
    }

    public function restore(User $user, SpotGuide $guide): bool
    {
        return $user->isOwner();
    }

    public function forceDelete(User $user, SpotGuide $guide): bool
    {
        return $user->isOwner();
    }
}
