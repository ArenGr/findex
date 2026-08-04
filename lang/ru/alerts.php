<?php

return [
    'heading' => 'Мои оповещения',
    'subtitle' => 'Получайте уведомления по email, в Telegram или в Viber, когда курс банка пересечёт заданный вами порог.',

    'status_created' => 'Оповещение создано. Вы получите уведомление, когда курс пересечёт ваш порог.',
    'status_deleted' => 'Оповещение удалено.',
    'status_telegram_disconnected' => 'Telegram отключён. Вы можете подключиться снова в любое время.',
    'status_viber_connected' => 'Viber подключён - оповещения будут приходить сюда.',
    'status_viber_disconnected' => 'Viber отключён. Вы можете подключиться снова в любое время.',

    'no_alerts' => 'У вас пока нет оповещений. Создайте одно ниже.',
    'any_organization' => 'Любой банк',
    'above' => 'выше',
    'below' => 'ниже',
    'active' => 'Активно',
    'paused' => 'Приостановлено',
    'pause' => 'Приостановить',
    'resume' => 'Возобновить',
    'delete' => 'Удалить',

    'existing_heading' => 'Ваши оповещения',
    'create_heading' => 'Создать новое оповещение',

    'form' => [
        'currency' => 'Валюта',
        'organization' => 'Банк',
        'rate_type' => 'Тип курса',
        'rate_field' => 'Курс',
        'direction' => 'Условие',
        'threshold' => 'Порог',
        'channel' => 'Уведомлять меня через',
        'channel_email' => 'Email',
        'channel_telegram' => 'Telegram',
        'channel_viber' => 'Viber',
        'telegram_not_connected_error' => 'Сначала подключите свой аккаунт Telegram, затем попробуйте снова.',
        'viber_not_connected_error' => 'Сначала подключите свой аккаунт Viber, затем попробуйте снова.',
        'submit' => 'Создать оповещение',
    ],

    'telegram_connect' => [
        'connected' => 'Telegram подключён - оповещения будут приходить сюда.',
        'not_connected' => 'Пока не подключён - подключите Telegram, чтобы получать оповещения этим способом.',
        'connect_button' => 'Подключить Telegram',
        'disconnect_button' => 'Отключить',
        'hint' => 'Откроет Telegram и начнёт чат с нашим ботом - так мы будем присылать вам оповещения по курсу.',
        'connected_confirmation' => 'Вы подключены! Оповещения по курсу теперь будут приходить сюда в виде сообщений Telegram.',
    ],

    'viber_connect' => [
        'connected' => 'Viber подключён - оповещения будут приходить сюда.',
        'not_connected' => 'Пока не подключён - подключите Viber, чтобы получать оповещения этим способом.',
        'connect_button' => 'Подключить Viber',
        'disconnect_button' => 'Отключить',
        'hint' => 'Свяжет ваш аккаунт, чтобы мы могли присылать вам оповещения по курсу в Viber.',
    ],

    'email' => [
        'subject' => 'Сработало оповещение по курсу :currency',
        'heading' => 'Ваше оповещение по курсу :currency сработало',
        'body' => 'Курс :field в :organization теперь :value, что соответствует заданному вами условию.',
        'view_organization' => 'Посмотреть организацию',
        'footer' => 'Вы установили это оповещение на :field :direction :threshold. ',
        'manage_alerts' => 'Управление оповещениями',
    ],
];
