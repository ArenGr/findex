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
                            {{ __('tourism.email.response_received_heading') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:20px;">
                            {{ __('tourism.email.response_received_body', ['organization' => $quoteResponse->organization->name]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-bottom:24px;">
                            <a
                                href="{{ $resultsUrl }}"
                                style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:12px 24px; font-size:14px; font-weight:bold;"
                            >
                                {{ __('tourism.email.view_results_button') }}
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
