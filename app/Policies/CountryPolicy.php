<?php

// Countries are shared house data. Contributors may create a missing one (inline from
// the guide form) and view them, but only owners may edit or delete existing ones.

namespace App\Policies;

use App\Models\Country;
use App\Models\User;

class CountryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function view(User $user, Country $country): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Country $country): bool
    {
        return $user->isOwner();
    }

    public function delete(User $user, Country $country): bool
    {
        return $user->isOwner();
    }
}
