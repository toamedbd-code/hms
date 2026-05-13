<?php

return [
    // Master switch to enable the sidebar full-menu override
    'force_full_menus' => env('FORCE_FULL_SIDEBAR', false),

    // When true, force full menus for all users when the override is enabled
    'force_for_all' => env('FORCE_FULL_SIDEBAR_FORCE_ALL', false),

    // Allow users with the `developer` role to see full menus when override is enabled
    // Default to false so developers do NOT receive unconditional full-menu access.
    'allow_developers' => env('FORCE_FULL_SIDEBAR_ALLOW_DEVS', false),

    // Comma-separated emails allowed to see full menus when override is enabled
    // Example: "toamedbd@gmail.com,admin@example.com"
    'emails' => env('FORCE_FULL_SIDEBAR_EMAILS', ''),
];
