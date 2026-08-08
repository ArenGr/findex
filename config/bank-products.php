<?php

/*
|--------------------------------------------------------------------------
| Sample rows for bank product pages that have no real data yet
|--------------------------------------------------------------------------
|
| These drive resources/views/banks/sample.blade.php - a page that shows a
| partner bank exactly where their product would appear and which fields we
| need from them, before any integration exists.
|
| Values are deliberately language-neutral (numbers, or a placeholder name
| like "Bank A") so the same row renders in every locale. Anything that
| would need translating - the column heading, its unit, the field
| descriptions - lives in the lang directory instead, keyed by the
| same category slug and matched to these columns by position.
|
| Never use a real bank's name here: these are invented figures, and
| attaching them to an actual institution would misrepresent its terms.
|
*/

return [

    'credit-cards' => [
        'rows' => [
            ['Bank A — Classic', '0', '1.0', '18', '45'],
            ['Bank B — Gold', '15 000', '3.0', '21', '55'],
            ['Bank C — Platinum', '50 000', '5.0', '24', '60'],
        ],
    ],

    'business-loans' => [
        'rows' => [
            ['Bank A — Working capital', '20 000 000', '14.0', '36', '1.0'],
            ['Bank B — Equipment', '50 000 000', '12.5', '60', '0.5'],
            ['Bank C — Micro business', '5 000 000', '16.0', '24', '1.5'],
        ],
    ],

    'student-loans' => [
        'rows' => [
            ['Bank A — Tuition', '3 000 000', '11.0', '60', '12'],
            ['Bank B — Study abroad', '10 000 000', '13.0', '84', '24'],
            ['Bank C — Short course', '1 000 000', '14.5', '24', '6'],
        ],
    ],

    'investing' => [
        'rows' => [
            ['Bank A — Government bonds', '100 000', '0.5', '0.2', '12'],
            ['Bank B — Managed portfolio', '1 000 000', '1.5', '0.3', '36'],
            ['Bank C — Brokerage account', '50 000', '0.0', '0.4', '—'],
        ],
    ],

];
