<?php

// Single owner admin account. These are the ONLY places ADMIN_EMAIL /
// ADMIN_PASSWORD env vars may be read — everything else reads config('admin.*')
// so the values survive `config:cache` in production. Unset locally → dev
// defaults (local login unchanged); set both in production (Laravel Cloud).

return [
    'email'    => env('ADMIN_EMAIL', 'seabound.souls@outlook.com'),
    'password' => env('ADMIN_PASSWORD', 'password'),
];
