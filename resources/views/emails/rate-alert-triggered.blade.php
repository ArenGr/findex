@php
    $fieldLabel = $alert->rate_field === 'buy_rate' ? __('organizations.buy') : __('organizations.sell');
    $directionLabel = __('alerts.' . $alert->direction);
    $value = $rate->{$alert->rate_field};

    // route() can't rely on URL::defaults(['locale' => ...]) here: that's set
    // by the SetLocale HTTP middleware, but this mail is built from a console
    // command with no active request, so {locale} must be passed explicitly.
    $mailLocale = config('localization.default');
@endphp
<x-emails.layout>
    <x-slot:unsubscribe>
        {{ __('alerts.email.footer', [
            'field' => $fieldLabel,
            'direction' => $directionLabel,
            'threshold' => number_format($alert->threshold, 2),
        ]) }}
        <a href="{{ route('alerts.index', ['locale' => $mailLocale]) }}" style="color:#607E34;">{{ __('alerts.email.manage_alerts') }}</a>
    </x-slot:unsubscribe>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($alert->user?->name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $alert->user->name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('alerts.email.heading', ['currency' => $alert->currency->code]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('alerts.email.body', [
                    'field' => $fieldLabel,
                    'value' => number_format($value, 2),
                    'organization' => $rate->organization->name,
                ]) }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:24px;">
                <a
                    href="{{ route('organizations.show', ['locale' => $mailLocale, 'organization' => $rate->organization]) }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('alerts.email.view_organization') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
