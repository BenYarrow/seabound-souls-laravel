<?php

// Blog tags are the owner's curated vocabulary — only owners manage them.
// Contributors author spot guides, not blogs, so they never touch tags.

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Tag $tag): bool
    {
        return $user->isOwner();
    }

    public function create(User $user): bool
    {
        return $user->isOwner();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isOwner();
    }
}
