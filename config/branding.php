<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Branding
    |--------------------------------------------------------------------------
    |
    | These values define the visual identity shown in authenticated menus
    | and auth pages. They can be overridden dynamically per contractor by
    | storing a "system_branding" array in session.
    |
    */
    'name' => env('BRANDING_NAME', env('APP_NAME', 'Veshop')),
    'short_name' => env('BRANDING_SHORT_NAME', 'VS'),
    'logo_url' => env('BRANDING_LOGO_URL', ''),
    'tagline' => env('BRANDING_TAGLINE', 'ERP para comércio e varejo'),
    'primary_color' => env('BRANDING_PRIMARY_COLOR', '#073341'),
    'accent_color' => env('BRANDING_ACCENT_COLOR', '#81D86F'),
];

