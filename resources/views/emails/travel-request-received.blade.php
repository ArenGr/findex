@php
    $request = $quoteResponse->quoteRequest;
@endphp

<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('tourism.email.agency_request_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:20px;">
                {{ __('tourism.email.agency_request_body') }}
            </td>
        </tr>

        {{-- The trip requirements only. No name, no email address - an
             agency needs what to price, not who is asking, until the
             traveller chooses to make contact. --}}
        <tr>
            <td style="font-size:14px; line-height:1.8; color:#262626; padding-bottom:24px;">
                <strong>{{ __('tourism.request.destination') }}:</strong>
                {{ \Illuminate\Support\Facades\Lang::has('destinations.' . $request->destination_country)
                    ? __('destinations.' . $request->destination_country)
                    : $request->destination_country }}<br>

                <strong>{{ __('tourism.request.summary_dates') }}:</strong>
                {{ $request->check_in->translatedFormat('d M Y') }} – {{ $request->check_out->translatedFormat('d M Y') }}<br>

                <strong>{{ __('tourism.request.summary_travelers') }}:</strong>
                {{ trans_choice('tourism.brief.adults', $request->adults, ['count' => $request->adults]) }}@if ($request->children > 0), {{ trans_choice('tourism.brief.children', $request->children, ['count' => $request->children]) }}@endif
            </td>
        </tr>

        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ $inboxUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('tourism.email.agency_request_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
