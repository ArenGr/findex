<?php

return [
    'heading' => 'Իմ ծանուցումները',
    'subtitle' => 'Ստացեք ծանուցում էլ. փոստով, Telegram-ով կամ Viber-ով, երբ բանկի փոխարժեքը հատի ձեր սահմանած շեմը։',

    'status_created' => 'Ծանուցումը ստեղծվել է։ Դուք կտեղեկացվեք, երբ փոխարժեքը հատի ձեր շեմը։',
    'status_deleted' => 'Ծանուցումը ջնջվել է։',
    'status_telegram_disconnected' => 'Telegram-ը անջատված է։ Կարող եք կրկին միանալ ցանկացած պահի։',
    'status_viber_connected' => 'Viber-ը միացված է. ծանուցումները կուղարկվեն այստեղ։',
    'status_viber_disconnected' => 'Viber-ը անջատված է։ Կարող եք կրկին միանալ ցանկացած պահի։',

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

    'modal' => [
        'subtitle' => 'Նշեք ցանկալի փոխարժեքը, և մենք կտեղեկացնենք հենց այն հասնելու պահին։',
        'sign_in_required' => 'Փոխարժեքի ծանուցումները կապված են ձեր հաշվի հետ, ուստի նախ մուտք գործեք - մենք ձեզ կվերադարձնենք հենց այս էջ։',
        'sign_in' => 'Մուտք գործել',
        'cancel' => 'Չեղարկել',
        'direction_below' => 'Իջնի ցածր',
        'direction_above' => 'Բարձրանա ավելի',
        'threshold_placeholder' => 'օր. 360.00',
        'current_rate' => 'Այս պահին լավագույնը',
        'manage' => 'Կառավարել ձեր ծանուցումները',
        'channel_question' => 'Ինչպե՞ս ձեզ տեղեկացնենք',
        'more_channels' => 'Միացրեք Telegram-ը կամ Viber-ը՝ այնտեղ ծանուցումներ ստանալու համար',
    ],
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
        'channel_viber' => 'Viber-ով',
        'telegram_not_connected_error' => 'Նախ միացրեք Ձեր Telegram հաշիվը, ապա նորից փորձեք։',
        'viber_not_connected_error' => 'Նախ միացրեք Ձեր Viber հաշիվը, ապա նորից փորձեք։',
        'submit' => 'Ստեղծել ծանուցում',
    ],

    'telegram_connect' => [
        'connected' => 'Telegram-ը միացված է. ծանուցումները կուղարկվեն այստեղ։',
        'not_connected' => 'Դեռ միացված չէ. միացրեք Telegram-ը՝ այս ձևով ծանուցումներ ստանալու համար։',
        'connect_button' => 'Միացնել Telegram',
        'disconnect_button' => 'Անջատել',
        'hint' => 'Բացում է Telegram-ը և սկսում զրույց մեր բոտի հետ. այսպես ենք ուղարկելու Ձեզ փոխարժեքի ծանուցումները։',
        'connected_confirmation' => 'Դուք միացված եք։ Փոխարժեքի ծանուցումներն այսուհետ կժամանեն այստեղ՝ Telegram հաղորդագրությունների տեսքով։',
    ],

    'viber_connect' => [
        'connected' => 'Viber-ը միացված է. ծանուցումները կուղարկվեն այստեղ։',
        'not_connected' => 'Դեռ միացված չէ. միացրեք Viber-ը՝ այս ձևով ծանուցումներ ստանալու համար։',
        'connect_button' => 'Միացնել Viber',
        'disconnect_button' => 'Անջատել',
        'hint' => 'Կապում է Ձեր հաշիվը, որպեսզի կարողանանք Ձեզ ուղարկել փոխարժեքի ծանուցումներ Viber-ով։',
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
