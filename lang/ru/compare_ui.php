<?php

// Shared by every compare page - see resources/views/components/results.
// Not results.php: Laravel's own pagination view calls __('results'), which would
// resolve to this whole array instead of the word.
return [
    'count' => '{0} Нет результатов|{1} 1 результат|[2,*] :count результатов',
    'sort_by' => 'Сортировка',
    'filters' => 'Фильтры',
    'clear_filters' => 'Сбросить фильтры',
    'empty_heading' => 'По этим фильтрам ничего нет',
    'empty_body' => 'Попробуйте расширить один из них или сбросьте их и начните заново.',
    'categories' => '{1} 1 категория|[2,*] :count категорий',
    'organizations' => '{1} 1 организация|[0,*] :count организаций',
    'insurers' => '{1} 1 страховщик|[0,*] :count страховщиков',
];
