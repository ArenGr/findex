<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($quote->organization?->name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('auto_insurance.email.interest_greeting', ['organization' => $quote->organization->name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('auto_insurance.email.interest_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:16px;">
                {{ __('auto_insurance.email.interest_body', [
                    'name' => $request->requester_name,
                    'plate' => $request->vehicle_plate,
                    'premium' => rtrim(rtrim((string) $quote->premium_amount, '0'), '.') . ' ' . $quote->premium_currency,
                ]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626;">
                {{ __('auto_insurance.email.interest_contact', ['email' => $request->requester_email]) }}
            </td>
        </tr>
    </table>
</x-emails.layout>
