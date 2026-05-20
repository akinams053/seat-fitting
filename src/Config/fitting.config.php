<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Bypass permission middleware
    |--------------------------------------------------------------------------
    |
    | When true, every fitting.* gate ability resolves to "allowed", which
    | effectively opens the plugin (Personal Fitting Check, Fitting Entry,
    | Fitting Groups, Corporation Skill Check) to every authenticated SeAT
    | user, regardless of which roles they hold.
    |
    | Intended for the staging / test server only — never enable in
    | production. Toggle via the `FITTING_BYPASS_PERMISSIONS` env var.
    |
    */
    'bypass_permissions' => env('FITTING_BYPASS_PERMISSIONS', false),
];
