<?php

return [
    'heading' => 'Мои оповещения',
    'subtitle' => 'Получайте уведомления по email или в Telegram, когда курс банка пересечёт заданный вами порог.',

    'status_created' => 'Оповещение создано. Вы получите уведомление, когда курс пересечёт ваш порог.',
    'status_deleted' => 'Оповещение удалено.',

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
        'telegram_chat_id' => 'Telegram chat ID',
        'telegram_help' => 'Напишите :bot в Telegram, затем вставьте сюда chat ID, которым он ответит.',
        'submit' => 'Создать оповещение',
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
