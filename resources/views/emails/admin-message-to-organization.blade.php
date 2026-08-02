<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                {{ __('emails.org_greeting', ['organization' => $organization->name]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:16px;">
                {{ $messageSubject }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; white-space:pre-line;">
                {{ $messageBody }}
            </td>
        </tr>
    </table>
</x-emails.layout>
