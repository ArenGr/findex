<?php

return [
    'heading' => 'Իմ ծանուցումները',
    'subtitle' => 'Ստացեք ծանուցում էլ. փոստով կամ Telegram-ով, երբ բանկի փոխարժեքը հատի ձեր սահմանած շեմը։',

    'status_created' => 'Ծանուցումը ստեղծվել է։ Դուք կտեղեկացվեք, երբ փոխարժեքը հատի ձեր շեմը։',
    'status_deleted' => 'Ծանուցումը ջնջվել է։',

    'no_alerts' => 'Դուք դեռ չունեք ծանուցումներ։ Ստեղծեք մեկը ստորև։',
    'any_organization' => 'Ցանկացած բանկ',
    'above' => 'բարձր',
    'below' => 'ցածր',
    'active' => 'Ակտիվ',
    'paused' => 'Դադարեցված',
    'pause' => 'Դադարեցնել',
    'resume' => 'Վերսկսել',
    'delete' => 'Ջնջել',

    'existing_heading' => 'Ձեր ծանուցումները',
    'create_heading' => 'Ստեղծել նոր ծանուցում',

    'form' => [
        'currency' => 'Արժույթ',
        'organization' => 'Բանկ',
        'rate_type' => 'Փոխարժեքի տեսակ',
        'rate_field' => 'Փոխարժեք',
        'direction' => 'Պայման',
        'threshold' => 'Շեմ',
        'channel' => 'Ծանուցել ինձ',
        'channel_email' => 'Էլ. փոստով',
        'channel_telegram' => 'Telegram-ով',
        'telegram_not_connected_error' => 'Նախ միացրեք Ձեր Telegram հաշիվը, ապա նորից փորձեք։',
        'submit' => 'Ստեղծել ծանուցում',
    ],

    'telegram_connect' => [
        'connected' => 'Telegram-ը միացված է. ծանուցումները կուղարկվեն այստեղ։',
        'not_connected' => 'Դեռ միացված չէ. միացրեք Telegram-ը՝ այս ձևով ծանուցումներ ստանալու համար։',
        'connect_button' => 'Միացնել Telegram',
        'hint' => 'Բացում է Telegram-ը և սկսում զրույց մեր բոտի հետ. այսպես ենք ուղարկելու Ձեզ փոխարժեքի ծանուցումները։',
        'connected_confirmation' => 'Դուք միացված եք։ Փոխարժեքի ծանուցումներն այսուհետ կժամանեն այստեղ՝ Telegram հաղորդագրությունների տեսքով։',
    ],

    'email' => [
        'subject' => ':currency փոխարժեքի ծանուցումն ակտիվացվել է',
        'heading' => 'Ձեր :currency փոխարժեքի ծանուցումն ակտիվացվել է',
        'body' => ':organization-ի :field փոխարժեքն այժմ :value է, ինչը համապատասխանում է ձեր սահմանած պայմանին։',
        'view_organization' => 'Դիտել կազմակերպությունը',
        'footer' => 'Դուք սահմանել եք այս ծանուցումը՝ :field :direction :threshold։ ',
        'manage_alerts' => 'Կառավարել ձեր ծանուցումները',
    ],
];
