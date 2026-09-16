@php
    $request = $visaResponse->visaRequest;
@endphp

<x-emails.layout>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('visa.email.agency_heading') }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:20px;">
                {{ __('visa.email.agency_body') }}
            </td>
        </tr>

        {{-- What the agency needs to price it, and nothing about the requester. --}}
        <tr>
            <td style="font-size:14px; line-height:1.8; color:#262626; padding-bottom:24px;">
                <strong>{{ __('visa.request.destination') }}:</strong> {{ $request->destination_label }}<br>
                <strong>{{ __('visa.show.dates') }}:</strong>
                {{ $request->travel_from->translatedFormat('d M Y') }} – {{ $request->travel_to->translatedFormat('d M Y') }}<br>
                <strong>{{ __('visa.request.applicants') }}:</strong> {{ $request->applicants }}
            </td>
        </tr>

        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ $respondUrl }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('visa.email.agency_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
