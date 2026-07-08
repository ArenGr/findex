@php
    $fieldLabel = $alert->rate_field === 'buy_rate' ? __('organizations.buy') : __('organizations.sell');
    $directionLabel = __('alerts.' . $alert->direction);
    $value = $rate->{$alert->rate_field};

    // route() can't rely on URL::defaults(['locale' => ...]) here: that's set
    // by the SetLocale HTTP middleware, but this mail is built from a console
    // command with no active request, so {locale} must be passed explicitly.
    $mailLocale = config('localization.default');
@endphp
<!DOCTYPE html>
<html>
<body style="margin:0; padding:0; background-color:#ffffff; font-family: Arial, Helvetica, sans-serif; color:#262626;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" style="max-width:480px;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="font-size:20px; font-weight:bold; color:#607E34; padding-bottom:24px;">
                            Findex
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                            {{ __('alerts.email.heading', ['currency' => $alert->currency->code]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:20px;">
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
                                style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:12px 24px; font-size:14px; font-weight:bold;"
                            >
                                {{ __('alerts.email.view_organization') }}
                            </a>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:12px; color:#a6a6a6; border-top:1px solid #d9d9d9; padding-top:16px;">
                            {{ __('alerts.email.footer', [
                                'field' => $fieldLabel,
                                'direction' => $directionLabel,
                                'threshold' => number_format($alert->threshold, 2),
                            ]) }}
                            <a href="{{ route('alerts.index', ['locale' => $mailLocale]) }}" style="color:#607E34;">{{ __('alerts.email.manage_alerts') }}</a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
