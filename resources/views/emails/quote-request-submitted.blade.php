<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($quoteRequest->requester_name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $quoteRequest->requester_name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('tourism.email.submitted_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('tourism.email.submitted_body', ['count' => $partnerCount]) }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:24px;">
                <a
                    href="{{ $resultsUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('tourism.email.view_results_button') }}
                </a>
            </td>
        </tr>
        <tr>
            <td style="font-size:12px; color:#a6a6a6; border-top:1px solid #eeeeea; padding-top:20px;">
                {{ __('tourism.results.bookmark_hint') }}
            </td>
        </tr>
    </table>
</x-emails.layout>
