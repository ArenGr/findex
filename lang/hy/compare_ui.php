<?php

// Shared by every compare page - see resources/views/components/results.
// Not results.php: Laravel's own pagination view calls __('results'), which would
// resolve to this whole array instead of the word.
return [
    'count' => '{0} Արդյունք չկա|{1} 1 արդյունք|[2,*] :count արդյունք',
    'sort_by' => 'Դասավորել',
    'filters' => 'Զտիչներ',
    'clear_filters' => 'Մաքրել զտիչները',
    'empty_heading' => 'Այս զտիչներին ոչինչ չի համապատասխանում',
    'empty_body' => 'Փորձեք ընդլայնել դրանցից մեկը կամ մաքրել և սկսել նորից։',
    'categories' => '{1} 1 կատեգորիա|[2,*] :count կատեգորիա',
];
