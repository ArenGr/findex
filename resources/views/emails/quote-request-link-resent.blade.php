<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($quoteRequests->first()?->requester_name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $quoteRequests->first()->requester_name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('tourism.email.resend_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('tourism.email.resend_intro') }}
            </td>
        </tr>

        @foreach ($quoteRequests as $quoteRequest)
            <tr>
                <td style="padding-bottom:16px; border-bottom:1px solid #eeeeea;">
                    <p style="font-size:14px; font-weight:bold; color:#161515; margin:0 0 4px 0;">
                        {{ __('tourism.results.trip_summary', [
                            'destination' => __('destinations.' . $quoteRequest->destination_country),
                            'check_in' => $quoteRequest->check_in->locale($quoteRequest->locale)->translatedFormat('d M'),
                            'check_out' => $quoteRequest->check_out->locale($quoteRequest->locale)->translatedFormat('d M Y'),
                            'adults' => $quoteRequest->adults,
                            'children' => $quoteRequest->children,
                        ]) }}
                    </p>
                    <a
                        href="{{ $quoteRequest->signedResultsUrl() }}"
                        style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px; margin-top:8px;"
                    >
                        {{ __('tourism.email.view_results_button') }}
                    </a>
                </td>
            </tr>
            <tr><td style="height:16px; line-height:16px; font-size:0;">&nbsp;</td></tr>
        @endforeach

        <tr>
            <td style="font-size:12px; color:#a6a6a6; padding-top:8px;">
                {{ __('tourism.results.bookmark_hint') }}
            </td>
        </tr>
    </table>
</x-emails.layout>
