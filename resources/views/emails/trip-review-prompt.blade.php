<x-emails.layout>
    @if ($unsubscribeUrl)
        <x-slot:unsubscribe>
            {!! __('tourism.email.review_prompt_unsubscribe', [
                'link' => '<a href="'.$unsubscribeUrl.'" style="color:#607E34;">'.__('tourism.email.unsubscribe_link_text').'</a>',
            ]) !!}
        </x-slot:unsubscribe>
    @endif

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
                {{ __('tourism.email.review_prompt_heading', [
                    'destination' => __('destinations.' . $quoteRequest->destination_country),
                ]) }}
            </td>
        </tr>
        <tr>
            <td style="font-size:14px; line-height:1.7; color:#262626; padding-bottom:24px;">
                {{ __('tourism.email.review_prompt_body') }}
            </td>
        </tr>

        @foreach ($organizations as $organization)
            <tr>
                <td style="padding-bottom:12px;">
                    <a
                        href="{{ route('organizations.show', ['locale' => $quoteRequest->locale, 'organization' => $organization]) }}"
                        style="display:inline-block; background-color:#607E34; color:#ffffff; text-decoration:none; padding:13px 28px; font-size:14px; font-weight:bold; border-radius:10px;"
                    >
                        {{ __('tourism.email.review_prompt_button', ['organization' => $organization->name]) }}
                    </a>
                </td>
            </tr>
        @endforeach
    </table>
</x-emails.layout>
