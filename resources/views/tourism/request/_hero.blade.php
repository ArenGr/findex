{{--
    Travel opens exactly like rates and insurance - see x-vertical-hero.

    It used to be a full-bleed photograph with a wash over it and a handwritten
    line across the sky. That was the single biggest reason the site read as
    several products: every other page opened with a headline and a panel, and
    this one opened with a picture.
--}}
<x-vertical-hero
    icon="plane"
    :eyebrow="__('tourism.request.eyebrow')"
    :title="__('tourism.request.hero_line1') . ' ' . __('tourism.request.hero_line2') . ' ' . __('tourism.request.hero_line2_accent')"
    :subtitle="__('tourism.request.subheading')"
    :signals="[
        ['icon' => 'shield-check', 'title' => __('tourism.request.benefit_trusted'), 'sub' => __('tourism.request.benefit_trusted_sub')],
        ['icon' => 'tag', 'title' => __('tourism.request.benefit_value'), 'sub' => __('tourism.request.benefit_value_sub')],
        ['icon' => 'clock', 'title' => __('tourism.request.benefit_time'), 'sub' => __('tourism.request.benefit_time_sub')],
    ]"
/>
