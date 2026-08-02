<?php

return [
    /*
     * The locale used when no locale segment is present or it doesn't
     * match a supported locale below.
     */
    'default' => 'hy',

    /*
     * Every locale the site is available in. Adding a new language later
     * is just: add an entry here + a matching lang/{locale} directory.
     */
    'available' => [
        'hy' => ['native' => 'Հայերեն', 'label' => 'Armenian', 'flag' => '🇦🇲'],
        'en' => ['native' => 'English', 'label' => 'English', 'flag' => '🇺🇸'],
        'ru' => ['native' => 'Русский', 'label' => 'Russian', 'flag' => '🇷🇺'],
    ],
];
