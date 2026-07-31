@props(['preheader' => null])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Findex</title>
</head>
<body style="margin:0; padding:0; background-color:#f2f2ee; font-family: Arial, Helvetica, sans-serif; color:#262626;">
    {{-- Hidden preview text shown next to the subject line in the inbox list,
    before the email is opened - falls back to nothing if $preheader is unset. --}}
    @if ($preheader)
        <div style="display:none; max-height:0; max-width:0; overflow:hidden; opacity:0; mso-hide:all;">
            {{ $preheader }}
        </div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f2f2ee;">
        <tr>
            <td align="center" style="padding:40px 16px;">
                <table role="presentation" width="100%" style="max-width:480px; background-color:#ffffff; border:1px solid #e5e5df; border-radius:16px;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding:36px 40px 4px 40px;">
                            <span style="font-family: Arial, Helvetica, sans-serif; font-size:26px; font-weight:bold; letter-spacing:0.5px; color:#607E34;">
                                Findex
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px 40px 40px 40px;">
                            {{ $slot }}
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" style="max-width:480px;" cellpadding="0" cellspacing="0">
                    <tr>
                        <td align="center" style="padding:24px 24px 0 24px; font-size:12px; line-height:1.6; color:#a6a6a6;">
                            {!! __('emails.footer_tagline') !!}
                        </td>
                    </tr>
                    <tr>
                        <td align="center" style="padding:10px 24px 0 24px; font-size:12px; line-height:1.6;">
                            <a href="{{ url('/') }}" style="color:#607E34; text-decoration:none;">{{ __('emails.footer_visit') }}</a>
                            <span style="color:#d9d9d9;">&nbsp;&middot;&nbsp;</span>
                            <a href="{{ route('contact', ['locale' => app()->getLocale()]) }}" style="color:#607E34; text-decoration:none;">{{ __('emails.footer_contact') }}</a>
                        </td>
                    </tr>
                    @isset($unsubscribe)
                        <tr>
                            <td align="center" style="padding:10px 24px 0 24px; font-size:12px; line-height:1.6; color:#a6a6a6;">
                                {{ $unsubscribe }}
                            </td>
                        </tr>
                    @endisset
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
