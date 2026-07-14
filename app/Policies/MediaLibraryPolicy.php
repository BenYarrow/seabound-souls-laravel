<?php

// Authorises media access. Owners see/manage everything; contributors are confined to
// their own uploads and never see house media (user_id null).

namespace App\Policies;

use App\Models\MediaLibrary;
use App\Models\User;

class MediaLibraryPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // list is query-scoped per role (see resource getEloquentQuery)
    }

    public function view(User $user, MediaLibrary $media): bool
    {
        return $user->isOwner() || $media->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MediaLibrary $media): bool
    {
        return $user->isOwner() || $media->user_id === $user->id;
    }

    public function delete(User $user, MediaLibrary $media): bool
    {
        return $user->isOwner() || $media->user_id === $user->id;
    }
}
