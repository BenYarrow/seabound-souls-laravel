<?php

// Contact enquiries contain visitor PII — owner-only.

namespace App\Policies;

use App\Models\ContactEnquiry;
use App\Models\User;

class ContactEnquiryPolicy
{
    public function viewAny(User $user): bool { return $user->isOwner(); }
    public function view(User $user, ContactEnquiry $enquiry): bool { return $user->isOwner(); }
    public function create(User $user): bool { return $user->isOwner(); }
    public function update(User $user, ContactEnquiry $enquiry): bool { return $user->isOwner(); }
    public function delete(User $user, ContactEnquiry $enquiry): bool { return $user->isOwner(); }
}
