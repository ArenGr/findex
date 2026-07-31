<x-emails.layout :preheader="__('auth.verify_email.body')">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center" style="padding-bottom:20px;">
                <table role="presentation" cellpadding="0" cellspacing="0">
                    <tr>
                        <td width="56" height="56" align="center" valign="middle" style="background-color:#607E34; border-radius:28px; font-family: Arial, Helvetica, sans-serif; font-size:22px; font-weight:bold; color:#ffffff; line-height:56px;">
                            &#10003;
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @if ($user->name)
            <tr>
                <td align="center" style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $user->name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td align="center" style="font-size:20px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('auth.verify_email.heading') }}
            </td>
        </tr>
        <tr>
            <td align="center" style="font-size:14px; line-height:1.7; color:#676767; padding-bottom:28px;">
                {{ __('auth.verify_email.body') }}
            </td>
        </tr>
        <tr>
            <td align="center" style="padding-bottom:24px;">
                <a
                    href="{{ $verificationUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:14px 32px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('auth.verify_email.button') }}
                </a>
            </td>
        </tr>
        <tr>
            <td align="center" style="font-size:13px; line-height:1.7; color:#676767; padding-bottom:16px;">
                {{ __('auth.verify_email.why') }}
            </td>
        </tr>
        <tr>
            <td align="center" style="font-size:13px; line-height:1.7; color:#676767; padding-bottom:24px;">
                {!! __('auth.verify_email.help', [
                    'link' => '<a href="'.route('contact', ['locale' => app()->getLocale()]).'" style="color:#607E34;">'.__('auth.verify_email.help_link_text').'</a>',
                ]) !!}
            </td>
        </tr>
        <tr>
            <td align="center" style="font-size:13px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {!! __('auth.verify_email.signoff') !!}
            </td>
        </tr>
        <tr>
            <td align="center" style="font-size:12px; line-height:1.6; color:#a6a6a6; border-top:1px solid #eeeeea; padding-top:20px;">
                {{ __('auth.verify_email.ignore_hint') }}
            </td>
        </tr>
    </table>
</x-emails.layout>
