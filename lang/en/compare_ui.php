<?php

// Shared by every compare page - see resources/views/components/results.
// Not results.php: Laravel's own pagination view calls __('results'), which would
// resolve to this whole array instead of the word.
return [
    'count' => '{0} No results|{1} 1 result|[2,*] :count results',
    'sort_by' => 'Sort by',
    'filters' => 'Filters',
    'clear_filters' => 'Clear filters',
    'empty_heading' => 'Nothing matches those filters',
    'empty_body' => 'Try widening one of them, or clear them and start again.',
    'categories' => '{1} 1 category|[2,*] :count categories',
    'organizations' => '{1} 1 organization|[0,*] :count organizations',
    'insurers' => '{1} 1 insurer|[0,*] :count insurers',
];
