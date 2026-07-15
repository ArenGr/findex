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
                            {{ __('auto_insurance.email.interest_heading') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:20px;">
                            {{ __('auto_insurance.email.interest_body', [
                                'name' => $request->requester_name,
                                'plate' => $request->vehicle_plate,
                                'premium' => rtrim(rtrim((string) $quote->premium_amount, '0'), '.') . ' ' . $quote->premium_currency,
                            ]) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:8px;">
                            {{ __('auto_insurance.email.interest_contact', ['email' => $request->requester_email]) }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
