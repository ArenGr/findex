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
                            {{ __('tourism.email.resend_heading') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:14px; line-height:1.6; color:#262626; padding-bottom:20px;">
                            {{ __('tourism.email.resend_intro') }}
                        </td>
                    </tr>

                    @foreach ($quoteRequests as $quoteRequest)
                        <tr>
                            <td style="padding-bottom:16px; border-bottom:1px solid #d9d9d9;">
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
                                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:10px 20px; font-size:13px; font-weight:bold; margin-top:8px;"
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
            </td>
        </tr>
    </table>
</body>
</html>
