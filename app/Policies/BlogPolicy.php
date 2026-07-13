<?php

// Blogs are house content — owner-only.

namespace App\Policies;

use App\Models\Blog;
use App\Models\User;

class BlogPolicy
{
    public function viewAny(User $user): bool { return $user->isOwner(); }
    public function view(User $user, Blog $blog): bool { return $user->isOwner(); }
    public function create(User $user): bool { return $user->isOwner(); }
    public function update(User $user, Blog $blog): bool { return $user->isOwner(); }
    public function delete(User $user, Blog $blog): bool { return $user->isOwner(); }
}
