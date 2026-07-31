<x-emails.layout>
    <x-slot:unsubscribe>
        {!! __('tourism.email.destination_available_unsubscribe', [
            'link' => '<a href="'.$unsubscribeUrl.'" style="color:#607E34;">'.__('tourism.email.unsubscribe_link_text').'</a>',
        ]) !!}
    </x-slot:unsubscribe>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        @if ($name)
            <tr>
                <td style="font-size:14px; color:#676767; padding-bottom:4px;">
                    {{ __('emails.greeting', ['name' => $name]) }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="font-size:18px; font-weight:bold; color:#161515; padding-bottom:12px;">
                {{ __('tourism.email.destination_available_heading', ['destination' => __('destinations.' . $destinationCountry)]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('tourism.email.destination_available_body', ['destination' => __('destinations.' . $destinationCountry)]) }}
            </td>
        </tr>
        <tr>
            <td style="padding-bottom:8px;">
                <a
                    href="{{ route('tourism.request', ['locale' => app()->getLocale()]) }}"
                    style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                >
                    {{ __('tourism.email.destination_available_button') }}
                </a>
            </td>
        </tr>
    </table>
</x-emails.layout>
