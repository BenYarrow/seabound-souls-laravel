<?php

// Handles an invited rider setting their initial password from a signed link.
// The 'signed' middleware on both routes guarantees the link is authentic and
// unexpired, so no prior auth is needed.

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class SetPasswordController extends Controller
{
    /** Show the set-password form for the invited rider. */
    public function show(User $user): View
    {
        return view('rider.set-password', ['user' => $user]);
    }

    /** Validate + save the password, log the rider in, send them to the panel. */
    public function store(Request $request, User $user): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        $user->update(['password' => Hash::make($request->string('password'))]);
        Auth::login($user);

        // Rotate the session id on login to prevent session fixation (CWE-384).
        $request->session()->regenerate();

        return redirect('/admin');
    }
}
