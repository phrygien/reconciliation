<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage Driver
    |--------------------------------------------------------------------------
    | 'session'  : préférences perdues à la déconnexion
    | 'database' : préférences persistées par utilisateur (multi-appareils)
    |
    | Pour 'database' :
    | 1. php artisan vendor:publish --tag=filament-ui-switcher-migrations && php artisan migrate
    | 2. Ajouter le trait HasUiPreferences au modèle User
    | 3. Ajouter 'ui_preferences' => 'array' dans les casts du modèle User
    */
    'driver' => env('UI_SWITCHER_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Database Column
    |--------------------------------------------------------------------------
    | Colonne JSON de la table users (utilisée uniquement avec le driver 'database').
    */
    'database_column' => 'ui_preferences',

    /*
    |--------------------------------------------------------------------------
    | Default Preferences
    |--------------------------------------------------------------------------
    | Valeurs appliquées tant que l'utilisateur n'a rien choisi, et utilisées
    | par le bouton « Réinitialiser ». Surchargeables via .env.
    */
    'defaults' => [
        'font'      => env('UI_SWITCHER_FONT', 'Inter'),
        'color'     => env('UI_SWITCHER_COLOR', '#6366f1'),
        'layout'    => env('UI_SWITCHER_LAYOUT', 'sidebar'),
        'font_size' => (int) env('UI_SWITCHER_FONT_SIZE', 16),
        'density'   => 'default',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Icon for Settings Panel
    |--------------------------------------------------------------------------
    */
    'icon' => 'heroicon-o-cog-6-tooth',

    /*
    |--------------------------------------------------------------------------
    | Available Fonts
    |--------------------------------------------------------------------------
    | Polices sans-serif lisibles pour interfaces de gestion (ERP / POS).
    | Les polices doivent être supportées par le font provider Filament
    | configuré (Bunny Fonts par défaut).
    */
    'fonts' => [
        'B612',
        'Inter',
        'Public Sans',
        'DM Sans',
        'Nunito Sans',
        'Roboto',
        'Poppins',
        'Open Sans',
        'Montserrat',
    ],

    /*
    |--------------------------------------------------------------------------
    | Font Provider
    |--------------------------------------------------------------------------
    | Laisser à null pour Bunny Fonts (par défaut).
    | Pour un fonctionnement hors-ligne / RGPD (utile pour une caisse),
    | héberger les polices en local :
    |
    | 'font_provider' => \Filament\FontProviders\LocalFontProvider::class,
    | 'font_url'      => '/css/fonts.css',
    */
    'font_provider' => null,
    'font_url'      => null,

    /*
    |--------------------------------------------------------------------------
    | Custom Colors
    |--------------------------------------------------------------------------
    | Palette sélectionnable. Teintes choisies pour garder un bon contraste
    | avec du texte blanc (jaune/lime retirés car peu lisibles sur boutons).
    */
    'custom_colors' => [
        '#6366f1', // indigo
        '#3b82f6', // bleu
        '#0ea5e9', // ciel
        '#06b6d4', // cyan
        '#14b8a6', // teal
        '#10b981', // émeraude
        '#22c55e', // vert
        '#f59e0b', // ambre
        '#f97316', // orange
        '#ef4444', // rouge
        '#ec4899', // rose
        '#8b5cf6', // violet
    ],

    /*
    |--------------------------------------------------------------------------
    | Available Layouts
    |--------------------------------------------------------------------------
    | 'sidebar-no-topbar' est désactivé : l'icône des réglages s'affiche dans
    | la topbar, l'utilisateur risquerait de ne plus pouvoir changer de layout.
    | Réactivez-le après test si l'icône reste accessible dans votre panel.
    */
    'layouts' => [
        'sidebar',
        'sidebar-collapsed',
        // 'sidebar-no-topbar',
        'topbar',
    ],

    /*
    |--------------------------------------------------------------------------
    | Font Size Range
    |--------------------------------------------------------------------------
    | Bornes du slider (px). 14 minimum pour rester lisible, 20 maximum
    | pour les écrans tactiles / caisse.
    */
    'font_size_range' => [
        'min' => 14,
        'max' => 20,
    ],
];
