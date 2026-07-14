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
                            {{ __('tourism.email.destination_available_heading', ['destination' => __('destinations.' . $destinationCountry)]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:20px;">
                            {{ __('tourism.email.destination_available_body', ['destination' => __('destinations.' . $destinationCountry)]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:8px;">
                            <a
                                href="{{ route('tourism.request', ['locale' => app()->getLocale()]) }}"
                                style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:10px 20px; font-size:13px; font-weight:bold;"
                            >
                                {{ __('tourism.email.destination_available_button') }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
