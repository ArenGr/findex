<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($exchangeQuoteResponse->exchangeQuoteRequest->requester_name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $exchangeQuoteResponse->exchangeQuoteRequest->requester_name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('exchange_quotes.email.response_received_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('exchange_quotes.email.response_received_body', ['organization' => $exchangeQuoteResponse->organization->name]) }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ $resultsUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('exchange_quotes.email.view_results_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
