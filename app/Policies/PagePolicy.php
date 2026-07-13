<?php

// Pages are house content — owner-only.

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool { return $user->isOwner(); }
    public function view(User $user, Page $page): bool { return $user->isOwner(); }
    public function create(User $user): bool { return $user->isOwner(); }
    public function update(User $user, Page $page): bool { return $user->isOwner(); }
    public function delete(User $user, Page $page): bool { return $user->isOwner(); }
}
