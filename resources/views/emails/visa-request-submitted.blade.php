<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('visa.email.submitted_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:20px;">
                {{ trans_choice('visa.email.submitted_body', $partnerCount, ['count' => $partnerCount]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.8; color:#262626; padding-bottom:24px;">
                <strong>{{ __('visa.request.destination') }}:</strong> {{ $visaRequest->destination_label }}<br>
                <strong>{{ __('visa.show.dates') }}:</strong>
                {{ $visaRequest->travel_from->translatedFormat('d M Y') }} – {{ $visaRequest->travel_to->translatedFormat('d M Y') }}<br>
                <strong>{{ __('visa.request.applicants') }}:</strong> {{ $visaRequest->applicants }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ $resultsUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('visa.email.submitted_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
