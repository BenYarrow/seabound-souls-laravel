<?php

// A contact-form submission, captured in the app so it's never lost if mail
// fails. Managed from the Filament admin (ContactEnquiryResource) via a simple
// new -> handled status.

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContactEnquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'email', 'message', 'status', 'handled_at',
    ];

    protected $casts = [
        'handled_at' => 'datetime',
    ];
}
