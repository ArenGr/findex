<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('visa.email.offer_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:20px;">
                {{ __('visa.email.offer_body', ['agency' => $visaResponse->organization->name]) }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ $resultsUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('visa.email.offer_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
