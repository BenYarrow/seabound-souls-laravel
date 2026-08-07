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

    // Filament's `authorize()` helper defaults a MISSING ability on an EXISTING
    // policy to allow (see vendor/filament/filament/src/helpers.php) — it only
    // enforces what the policy actually declares. PhotographerResource ships a
    // DeleteBulkAction (-> deleteAny) and Photographer uses SoftDeletes (->
    // restore/restoreAny/forceDelete/forceDeleteAny are reachable the moment a
    // trashed-records UI or a direct route is added). Without these, every one
    // of them was implicitly open to any authenticated panel user. The
    // resource has no ->reorderable() or ReplicateAction, so `reorder` and
    // `replicate` aren't real abilities for it and are deliberately omitted.

    public function deleteAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function restore(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function restoreAny(User $user): bool
    {
        return $user->isOwner();
    }

    public function forceDelete(User $user, Photographer $photographer): bool
    {
        return $user->isOwner();
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->isOwner();
    }
}
