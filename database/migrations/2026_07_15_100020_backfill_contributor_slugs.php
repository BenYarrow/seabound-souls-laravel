<?php

// Backfills `slug` for contributors created before slug generation existed
// (sub-project 1 was invite-only, pre-dating the public-profile feature).
// Slug generation is lazy — only the `saving` hook on App\Models\User writes
// it, and only when blank — so any contributor who hasn't been re-saved since
// still has slug = null. `hasPublicProfile()`/`scopeWithPublicProfile()` don't
// check slug, so a null-slug contributor with a published guide would surface
// in the contributor roll-up linking to `/contributors/null` (404). Re-saving
// each affected row here triggers the model's own hook, so slug generation
// logic lives in exactly one place.

use App\Models\User;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        User::query()
            ->where('role', User::ROLE_CONTRIBUTOR)
            ->whereNull('slug')
            ->get()
            ->each(function (User $user) {
                if ($user->first_name || $user->last_name) {
                    $user->save(); // triggers the saving hook → slug generated
                }
            });
    }

    /**
     * Irreversible data backfill — nulling slugs back out would just
     * reintroduce the broken-link bug this migration exists to fix.
     */
    public function down(): void
    {
        // no-op
    }
};
