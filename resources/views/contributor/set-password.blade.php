{{-- Minimal standalone set-password page for invited contributors (outside the SPA). --}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set your password — Seabound Sessions</title>
    @vite('resources/css/app.css')
</head>
<body class="min-h-full bg-cream flex items-center justify-center p-6">
    <div class="w-full max-w-md rounded-xl bg-white shadow p-8">
        <h1 class="text-2xl font-title text-primary mb-2">Set your password</h1>
        <p class="text-sm text-secondary/70 mb-6">Welcome aboard, {{ $user->name }}. Choose a password to activate your Contributor account.</p>

        @if ($errors->any())
            <div class="mb-4 rounded bg-red-50 p-3 text-sm text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ url()->signedRoute('contributor.password.store', ['user' => $user->id], now()->addDays(7)) }}">
            @csrf
            <label class="block text-sm font-medium mb-1" for="password">Password</label>
            <input id="password" name="password" type="password" required
                   class="w-full rounded border border-gray-300 px-3 py-2 mb-4" autocomplete="new-password">

            <label class="block text-sm font-medium mb-1" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required
                   class="w-full rounded border border-gray-300 px-3 py-2 mb-6" autocomplete="new-password">

            <button type="submit" class="w-full rounded bg-primary px-4 py-2 text-white font-medium hover:bg-primary-darker">
                Activate account
            </button>
        </form>
    </div>
</body>
</html>
