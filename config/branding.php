<?php

return [
    /*
    |--------------------------------------------------------------------------
    | System Branding
    |--------------------------------------------------------------------------
    |
    | These values define the visual identity shown in authenticated menus,
    | auth pages and public landing pages.
    |
    */
    'name' => env('BRANDING_NAME', env('APP_NAME', 'Veshop')),
    'short_name' => env('BRANDING_SHORT_NAME', 'VS'),
    'logo_url' => env('BRANDING_LOGO_URL', ''),
    'icon_url' => env('BRANDING_ICON_URL', '/brand/icone-veshop.png'),
    'favicon_url' => env('BRANDING_FAVICON_URL', '/brand/favicon-veshop.ico'),
    'tagline' => env('BRANDING_TAGLINE', 'ERP para comércio e serviços'),
    'primary_color' => env('BRANDING_PRIMARY_COLOR', '#073341'),
    'accent_color' => env('BRANDING_ACCENT_COLOR', '#81D86F'),
    'landing_images' => [
        'about' => env('BRANDING_LANDING_ABOUT_IMAGE', '/landing/images/about.png'),
        'why_choose' => env('BRANDING_LANDING_WHY_CHOOSE_IMAGE', '/landing/images/working.png'),
        'work' => env('BRANDING_LANDING_WORK_IMAGE', '/landing/images/group-working.png'),
    ],
];
