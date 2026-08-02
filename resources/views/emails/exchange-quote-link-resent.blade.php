<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($exchangeQuoteRequests->first()?->requester_name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $exchangeQuoteRequests->first()->requester_name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('exchange_quotes.email.resend_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('exchange_quotes.email.resend_intro') }}
            </td>
        </tr>

        @foreach ($exchangeQuoteRequests as $exchangeQuoteRequest)
            <tr>
                <td style="padding-bottom:16px; border-bottom:1px solid #eeeeea;">
                    <p style="font-size:14px; font-weight:bold; color:#161515; margin:0 0 4px 0;">
                        {{ number_format((float) $exchangeQuoteRequest->amount, 2) }} {{ $exchangeQuoteRequest->currency->code }}
                        &middot;
                        {{ __('exchange_quotes.request.direction_' . $exchangeQuoteRequest->rate_field, ['currency' => $exchangeQuoteRequest->currency->code], $exchangeQuoteRequest->locale) }}
                    </p>
                    <a
                        href="{{ $exchangeQuoteRequest->signedResultsUrl() }}"
                        style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px; margin-top:8px;"
                    >
                        {{ __('exchange_quotes.email.view_results_button') }}
                    </a>
                </td>
            </tr>
            <tr><td style="height:16px; line-height:16px; font-size:0;">&nbsp;</td></tr>
        @endforeach

        <tr>
            <td style="font-size:12px; color:#a6a6a6; padding-top:8px;">
                {{ __('exchange_quotes.results.bookmark_hint') }}
            </td>
        </tr>
    </table>
</x-emails.layout>
