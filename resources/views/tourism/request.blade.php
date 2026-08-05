@extends('layouts.app')

@section('title', __('tourism.request.heading') . ' — Findex')

@php
    $steps = [
        ['title' => __('tourism.request.step_1_title'), 'body' => __('tourism.request.step_1_body'), 'color' => 'slide-green'],
        ['title' => __('tourism.request.step_2_title'), 'body' => __('tourism.request.step_2_body'), 'color' => 'slide-blue'],
        ['title' => __('tourism.request.step_3_title'), 'body' => __('tourism.request.step_3_body'), 'color' => 'accent-yellow'],
    ];
    // Referenced from both the slider's own max attribute and its initial
    // x-data value below - kept in one place since a plain JS object
    // literal can't have one property's initializer read a sibling
    // property via `this` before the object finishes constructing.
    $budgetSliderMax = 3000000;
@endphp

@section('content')
    {{-- Hero --}}
    <section class="border-b border-placeholder bg-primary/5">
        <div class="mx-auto max-w-3xl px-6 py-16 text-center lg:px-10">
            <span class="inline-flex rounded-full bg-slide-green/20 px-4 py-2 text-sm font-medium text-ink">
                {{ __('tourism.request.badge') }}
            </span>

            <h1 class="mt-6 font-heading text-3xl leading-tight font-bold text-ink sm:text-4xl">{{ __('tourism.request.heading') }}</h1>
            <p class="mx-auto mt-4 max-w-xl text-base leading-relaxed text-muted">{{ __('tourism.request.subheading') }}</p>

            <div class="mx-auto mt-10 grid grid-cols-1 gap-4 text-left sm:grid-cols-3">
                @foreach ($steps as $i => $step)
                    <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-placeholder/60">
                        <span class="flex h-8 w-8 items-center justify-center rounded-full bg-placeholder/20 font-heading text-xs font-bold" style="color: var(--color-{{ $step['color'] }})">
                            {{ $i + 1 }}
                        </span>
                        <p class="mt-3 text-sm font-semibold text-ink">{{ $step['title'] }}</p>
                        <p class="mt-1 text-xs leading-relaxed text-muted">{{ $step['body'] }}</p>
                    </div>
                @endforeach
            </div>

            <a href="{{ route('travel-agencies') }}" class="mt-8 inline-block text-sm font-medium text-primary hover:underline">
                {{ __('travel_agencies.browse_link') }} →
            </a>
        </div>
    </section>

    {{-- max-w-7xl to match the home page - the extra room goes to a two-column layout (form left, sticky summary+submit sidebar right), not just a wider single column. --}}
    <section class="mx-auto max-w-7xl px-6 py-16 lg:px-10">
        @if (session('status') === 'destination-alert-created')
            <div class="mx-auto mb-6 max-w-3xl rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-sm text-primary">
                {{ __('tourism.request.notify_me_confirmed') }}
            </div>
        @endif

        @if (session('status') === 'email-verification-required')
            <div class="mx-auto mb-6 max-w-3xl rounded-lg border border-accent-yellow/40 bg-accent-yellow/10 px-4 py-3 text-sm text-ink">
                {{ __('auth.verify_email.action_blocked') }}
            </div>
        @endif

        {{-- One shared x-data for the whole form (see loan-affordability-calculator.blade.php for the same convention) so the voice concierge, destination picker, budget slider, and summary card all read/write the same state. --}}
        <form
            method="POST"
            action="{{ route('tourism.request.store') }}"
            novalidate
            x-data="{
                state: 'idle',
                error: '',
                applied: null,
                mediaRecorder: null,
                chunks: [],
                stream: null,
                seconds: 0,
                timer: null,
                maxSeconds: 60,
                audioContext: null,
                analyser: null,
                levelRaf: null,
                level: 0,

                destination: '{{ old('destination_country') }}',
                countries: @js($countries),
                typicalPrices: @js($typicalPrices),
                destSearch: '',
                destOpen: false,

                checkIn: @js(old('check_in', '')),
                checkOut: @js(old('check_out', '')),
                adults: {{ (int) old('adults', 2) }},
                children: {{ (int) old('children', 0) }},
                allInclusive: {{ old('all_inclusive') ? 'true' : 'false' }},
                insurance: {{ old('insurance') ? 'true' : 'false' }},

                budgetSliderMax: {{ $budgetSliderMax }},
                {{-- min() against $budgetSliderMax - the DB column (and so
                old()) allows a far larger value than this UI's slider
                does; same reasoning as applyVoiceFields() below. --}}
                budgetMin: {{ old('budget_min_amd') ? min((int) old('budget_min_amd'), $budgetSliderMax) : 0 }},
                budgetMax: {{ old('budget_max_amd') ? min((int) old('budget_max_amd'), $budgetSliderMax) : $budgetSliderMax }},
                budgetTouched: {{ (old('budget_min_amd') || old('budget_max_amd')) ? 'true' : 'false' }},
                // The slider itself always drags in AMD (matching what
                // store() actually validates/saves) - budgetCurrency only
                // changes how that same underlying value is DISPLAYED, via
                // formatBudget() below. currencyRates is AMD-per-1-unit for
                // each currency (see QuoteRequestController::create()); a
                // currency only appears here if a real rate was available.
                budgetCurrency: '{{ $defaultBudgetCurrency }}',
                currencyRates: @js($currencyRates),

                get formattedTime() {
                    const m = Math.floor(this.seconds / 60).toString().padStart(2, '0');
                    const s = (this.seconds % 60).toString().padStart(2, '0');
                    return `${m}:${s}`;
                },
                get selectedCountry() {
                    return this.countries.find((c) => c.code === this.destination) ?? null;
                },
                get filteredCountries() {
                    if (!this.destSearch) return this.countries;
                    const q = this.destSearch.toLowerCase();
                    return this.countries.filter((c) => c.name.toLowerCase().includes(q));
                },
                selectDestination(country) {
                    this.destination = country.code;
                    this.destSearch = '';
                    this.destOpen = false;
                },
                get nights() {
                    if (!this.checkIn || !this.checkOut) return null;
                    const diff = Math.round((new Date(this.checkOut) - new Date(this.checkIn)) / 86400000);
                    return diff > 0 ? diff : null;
                },
                get travelersLabel() {
                    const adultsWord = this.adults === 1 ? @js(__('tourism.request.summary_adult_singular')) : @js(__('tourism.request.adults'));
                    const parts = [`${this.adults} ${adultsWord}`];
                    if (this.children > 0) {
                        const childrenWord = this.children === 1 ? @js(__('tourism.request.summary_child_singular')) : @js(__('tourism.request.children'));
                        parts.push(`${this.children} ${childrenWord}`);
                    }
                    return parts.join(', ');
                },
                // Converts an AMD amount into whatever budgetCurrency is
                // currently selected and formats it - the one place both
                // the slider's own labels and the summary sidebar's budget
                // line pull from, so they can never drift out of sync with
                // each other.
                formatBudget(amdAmount) {
                    const rate = this.currencyRates[this.budgetCurrency] ?? 1;
                    const converted = this.budgetCurrency === 'AMD' ? amdAmount : amdAmount / rate;
                    // AMD has no minor unit worth showing; foreign
                    // currencies round to whole units too - this is a
                    // rough slider figure, not a priced quote.
                    return new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(converted) + ' ' + this.budgetCurrency;
                },
                get budgetLabel() {
                    if (!this.budgetTouched) return null;
                    if (this.budgetMax >= this.budgetSliderMax) {
                        return `${@js(__('tourism.request.summary_budget_from'))} ${this.formatBudget(this.budgetMin)}+`;
                    }
                    return `${this.formatBudget(this.budgetMin)} – ${this.formatBudget(this.budgetMax)}`;
                },

                async start() {
                    this.error = '';
                    this.applied = null;
                    try {
                        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                    } catch (e) {
                        this.state = 'error';
                        this.error = @js(__('tourism.request.voice_fill_mic_error'));
                        return;
                    }
                    const mimeType = ['audio/webm', 'audio/ogg', 'audio/mp4'].find((t) => window.MediaRecorder && MediaRecorder.isTypeSupported(t)) || '';
                    this.chunks = [];
                    this.mediaRecorder = mimeType ? new MediaRecorder(this.stream, { mimeType }) : new MediaRecorder(this.stream);
                    this.mediaRecorder.ondataavailable = (e) => { if (e.data.size > 0) this.chunks.push(e.data); };
                    this.mediaRecorder.onstop = () => this.submitRecording();
                    this.mediaRecorder.start();
                    this.startLevelMeter();
                    this.state = 'recording';
                    this.seconds = 0;
                    this.timer = setInterval(() => {
                        this.seconds++;
                        if (this.seconds >= this.maxSeconds) this.stop();
                    }, 1000);
                },
                // A live mic-level meter while recording - the most common
                // reason nothing ends up filled in isn't a bad recording,
                // it's the wrong input device selected or a muted mic, and
                // this makes that obvious immediately instead of only after
                // waiting on a pointless round trip to the server.
                startLevelMeter() {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) return;
                    this.audioContext = new AudioCtx();
                    const source = this.audioContext.createMediaStreamSource(this.stream);
                    this.analyser = this.audioContext.createAnalyser();
                    this.analyser.fftSize = 256;
                    source.connect(this.analyser);
                    const data = new Uint8Array(this.analyser.frequencyBinCount);
                    const tick = () => {
                        if (!this.analyser) return;
                        this.analyser.getByteFrequencyData(data);
                        const avg = data.reduce((a, b) => a + b, 0) / data.length;
                        this.level = Math.min(1, avg / 60);
                        this.levelRaf = requestAnimationFrame(tick);
                    };
                    tick();
                },
                stopLevelMeter() {
                    if (this.levelRaf) cancelAnimationFrame(this.levelRaf);
                    this.levelRaf = null;
                    this.analyser = null;
                    this.audioContext?.close();
                    this.audioContext = null;
                    this.level = 0;
                },
                stop() {
                    clearInterval(this.timer);
                    this.stream?.getTracks().forEach((t) => t.stop());
                    this.stopLevelMeter();
                    if (this.seconds < 1) {
                        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                            this.mediaRecorder.onstop = null;
                            this.mediaRecorder.stop();
                        }
                        this.state = 'error';
                        this.error = @js(__('tourism.request.voice_fill_too_short'));
                        return;
                    }
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                        this.state = 'processing';
                        this.mediaRecorder.stop();
                    }
                },
                cancel() {
                    clearInterval(this.timer);
                    this.stopLevelMeter();
                    if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
                        this.mediaRecorder.onstop = null;
                        this.mediaRecorder.stop();
                    }
                    this.stream?.getTracks().forEach((t) => t.stop());
                    this.state = 'idle';
                },
                async submitRecording() {
                    const blob = new Blob(this.chunks, { type: this.mediaRecorder.mimeType || 'audio/webm' });
                    const formData = new FormData();
                    formData.append('audio', blob, 'trip-request.webm');
                    try {
                        const response = await fetch(@js(route('tourism.voice-fill')), {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                Accept: 'application/json',
                            },
                            body: formData,
                        });
                        if (!response.ok) throw new Error('voice-fill request failed');
                        const data = await response.json();
                        this.applyVoiceFields(data);
                        this.state = 'idle';
                        this.applied = data.found ? 'applied' : 'empty';
                    } catch (e) {
                        this.state = 'error';
                        this.error = @js(__('tourism.request.voice_fill_error'));
                    }
                },
                applyVoiceFields(data) {
                    if (data.destination_country) this.destination = data.destination_country;
                    if (data.hotel_name) document.getElementById('hotel_name').value = data.hotel_name;
                    if (data.check_in) this.checkIn = data.check_in;
                    if (data.check_out) this.checkOut = data.check_out;
                    if (data.adults !== null && data.adults !== undefined) this.adults = data.adults;
                    if (data.children !== null && data.children !== undefined) this.children = data.children;
                    {{-- Both clamped to budgetSliderMax - the extraction service allows values above the slider's own cap, which would otherwise desync the filled-track percentage from the pinned thumb. --}}
                    if (typeof data.budget_min_amd === 'number') { this.budgetMin = Math.min(data.budget_min_amd, this.budgetSliderMax); this.budgetTouched = true; }
                    if (typeof data.budget_max_amd === 'number') { this.budgetMax = Math.min(data.budget_max_amd, this.budgetSliderMax); this.budgetTouched = true; }
                    if (data.notes) document.getElementById('notes').value = data.notes;
                    if (typeof data.all_inclusive === 'boolean') this.allInclusive = data.all_inclusive;
                    if (typeof data.insurance === 'boolean') this.insurance = data.insurance;
                },
                // Undoes applyVoiceFields() - resets every field it could
                // have touched back to the form's own defaults, not just
                // blank, so a required field like adults doesn't end up
                // empty.
                clearVoiceFields() {
                    this.destination = '';
                    document.getElementById('hotel_name').value = '';
                    this.checkIn = '';
                    this.checkOut = '';
                    this.adults = 2;
                    this.children = 0;
                    this.budgetMin = 0;
                    this.budgetMax = this.budgetSliderMax;
                    this.budgetTouched = false;
                    document.getElementById('notes').value = '';
                    this.allInclusive = false;
                    this.insurance = false;
                    this.applied = null;
                },
            }"
        >
            @csrf

            {{-- Honeypot: hidden from real visitors, a bot filling every field trips it (see QuoteRequestController::store) --}}
            <div class="hidden" aria-hidden="true">
                <label for="company">Company</label>
                <input type="text" name="company" id="company" tabindex="-1" autocomplete="off">
            </div>

            {{-- Two columns on large screens: form left, sticky summary+consent+submit sidebar right (items-start stops the taller left column from stretching it). Collapses to one column below lg. --}}
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-[1fr_380px] lg:items-start">
                <div class="space-y-8">
            {{-- Voice concierge card - writes into the same x-data as the rest of the form via applyVoiceFields(). --}}
            <div class="rounded-2xl border border-primary/20 bg-primary/5 p-5 shadow-sm sm:p-6">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                            <rect x="9" y="2" width="6" height="12" rx="3" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-ink">{{ __('tourism.request.voice_fill_card_heading') }}</p>
                        <p class="text-xs text-muted">{{ __('tourism.request.voice_fill_hint') }}</p>
                        <p class="mt-1 text-xs text-subtle italic">{{ __('tourism.request.voice_fill_example') }}</p>

                        <div class="mt-4">
                            <template x-if="state === 'idle'">
                                <button type="button" @click="start()" class="flex items-center gap-2 rounded-full bg-primary px-4 py-2 text-xs font-medium text-white transition hover:bg-primary-dark">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="2">
                                        <rect x="9" y="2" width="6" height="12" rx="3" stroke-linecap="round" stroke-linejoin="round" />
                                        <path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    {{ __('tourism.request.voice_fill_start') }}
                                </button>
                            </template>

                            <template x-if="state === 'recording'">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div class="flex items-center gap-2 text-sm text-ink">
                                        <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-red-500"></span>
                                        <span>{{ __('tourism.request.voice_fill_recording') }}</span>
                                        <span class="text-muted tabular-nums" x-text="formattedTime"></span>
                                        {{-- Live mic-level meter: bars scale off the same `level` value so a
                                        silent/muted mic is obvious immediately, not after a wasted round trip. --}}
                                        <span class="flex h-3 items-end gap-0.5" aria-hidden="true">
                                            <span class="w-1 rounded-full bg-primary transition-all" :style="`height: ${Math.max(15, level * 100)}%`"></span>
                                            <span class="w-1 rounded-full bg-primary transition-all" :style="`height: ${Math.max(15, level * 75)}%`"></span>
                                            <span class="w-1 rounded-full bg-primary transition-all" :style="`height: ${Math.max(15, level * 100)}%`"></span>
                                        </span>
                                    </div>
                                    <div class="flex gap-2">
                                        <button type="button" @click="cancel()" class="rounded-full border border-border-muted px-3 py-1.5 text-xs text-muted hover:bg-white">
                                            {{ __('tourism.request.voice_fill_cancel') }}
                                        </button>
                                        <button type="button" @click="stop()" class="rounded-full bg-primary px-4 py-1.5 text-xs font-medium text-white hover:bg-primary-dark">
                                            {{ __('tourism.request.voice_fill_stop') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="state === 'processing'">
                                <div class="flex items-center gap-2 text-sm text-ink">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 shrink-0 animate-spin text-primary">
                                        <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" fill="none" />
                                        <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none" />
                                    </svg>
                                    <span>{{ __('tourism.request.voice_fill_processing') }}</span>
                                </div>
                            </template>

                            <template x-if="state === 'error'">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <p class="text-sm text-red-600" x-text="error"></p>
                                    <button type="button" @click="state = 'idle'; error = ''" class="shrink-0 rounded-full border border-red-300 px-3 py-1.5 text-xs text-red-600 hover:bg-red-50">
                                        {{ __('tourism.request.voice_fill_retry') }}
                                    </button>
                                </div>
                            </template>

                            <div x-show="applied === 'applied'" x-cloak x-transition class="flex flex-wrap items-center justify-between gap-2">
                                <p class="flex items-center gap-1.5 text-xs font-medium text-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 shrink-0">
                                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ __('tourism.request.voice_fill_applied') }}
                                </p>
                                <button type="button" @click="clearVoiceFields()" class="shrink-0 rounded-full border border-border-muted px-3 py-1 text-xs text-muted hover:bg-white">
                                    {{ __('tourism.request.voice_fill_clear') }}
                                </button>
                            </div>

                            <p x-show="applied === 'empty'" x-cloak x-transition class="flex items-start gap-1.5 text-xs font-medium text-amber-700">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-3.5 w-3.5 shrink-0">
                                    <path fill-rule="evenodd" d="M8.3 3.3a2 2 0 0 1 3.4 0l6.4 10.9A2 2 0 0 1 16.4 17H3.6a2 2 0 0 1-1.7-2.9L8.3 3.3ZM10 7a.9.9 0 0 0-.9.9v3.3a.9.9 0 1 0 1.8 0V7.9A.9.9 0 0 0 10 7Zm0 7.8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" />
                                </svg>
                                {{ __('tourism.request.voice_fill_nothing_found') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-placeholder bg-white p-6 shadow-sm sm:p-8">
                <div class="space-y-8">
                    {{-- 1. Trip --}}
                    <div>
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slide-green/20 font-heading text-sm font-bold text-ink">1</span>
                            <p class="text-sm font-semibold text-ink">{{ __('tourism.request.section_trip') }}</p>
                        </div>

                        {{-- Searchable dropdown over every ISO country, not just curated destinations - store() still checks live partner availability and offers the "notify me" fallback below for anything unmatched. --}}
                        <div class="mt-4" @click.outside="destOpen = false">
                            <label class="block text-sm font-medium text-ink">{{ __('tourism.request.destination') }}</label>

                            @error('destination_country')
                                <div class="mt-2 rounded-lg border border-placeholder bg-placeholder/10 p-3">
                                    <p class="text-xs text-red-600">{{ $message }}</p>
                                    <div class="mt-2 flex flex-wrap items-end gap-2">
                                        @guest
                                            <div class="min-w-0 flex-1">
                                                <label for="alert_email" class="block text-xs font-medium text-ink">{{ __('tourism.request.notify_me_email_label') }}</label>
                                                <input
                                                    type="email"
                                                    name="email"
                                                    id="alert_email"
                                                    form="notify-me-form"
                                                    required
                                                    placeholder="{{ __('auth.email') }}"
                                                    class="mt-1 block w-full rounded-md border border-border-muted px-2 py-1.5 text-sm text-ink focus:border-primary focus:outline-none"
                                                >
                                            </div>
                                        @else
                                            <p class="flex-1 text-xs text-muted">{{ __('tourism.request.notify_me_hint') }}</p>
                                        @endguest

                                        <button type="submit" form="notify-me-form" class="shrink-0 rounded-md border border-primary px-3 py-1.5 text-xs font-medium text-primary hover:bg-primary/5">
                                            {{ __('tourism.request.notify_me_button') }}
                                        </button>
                                    </div>
                                </div>
                            @enderror

                            <div class="relative mt-1.5">
                                <input type="hidden" name="destination_country" :value="destination">

                                <button
                                    type="button"
                                    @click="destOpen = !destOpen; $nextTick(() => destOpen && $refs.destinationSearch.focus())"
                                    class="flex w-full items-center gap-2 rounded-lg border px-3 py-2.5 text-sm focus:outline-none {{ $errors->has('destination_country') ? 'border-red-400' : 'border-border-muted focus:border-primary' }}"
                                    :aria-expanded="destOpen"
                                >
                                    <template x-if="selectedCountry">
                                        <span class="flex min-w-0 items-center gap-2">
                                            <span class="text-lg leading-none" x-text="selectedCountry.flag"></span>
                                            <span class="truncate text-ink" x-text="selectedCountry.name"></span>
                                        </span>
                                    </template>
                                    <span x-show="!selectedCountry" class="text-subtle">{{ __('tourism.request.destination_placeholder') }}</span>
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 12 8" class="ml-auto h-2 w-3 shrink-0 fill-none stroke-current text-subtle transition-transform" :class="{ 'rotate-180': destOpen }">
                                        <path d="M1 1.5 6 6.5 11 1.5" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </button>

                                <div
                                    x-show="destOpen"
                                    x-cloak
                                    x-transition
                                    class="absolute z-20 mt-1.5 w-full rounded-lg border border-placeholder bg-white shadow-lg"
                                >
                                    <div class="p-2">
                                        <input
                                            type="text"
                                            x-model="destSearch"
                                            x-ref="destinationSearch"
                                            placeholder="{{ __('tourism.request.destination_search_placeholder') }}"
                                            class="block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
                                            @keydown.escape="destOpen = false"
                                        >
                                    </div>
                                    <ul class="max-h-64 overflow-y-auto px-2 pb-2">
                                        <template x-for="country in filteredCountries" :key="country.code">
                                            <li>
                                                <button
                                                    type="button"
                                                    @click="selectDestination(country)"
                                                    class="flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-sm hover:bg-primary/5"
                                                    :class="destination === country.code ? 'bg-primary/5 font-medium text-primary' : 'text-ink'"
                                                >
                                                    <span class="text-lg leading-none" x-text="country.flag"></span>
                                                    <span x-text="country.name"></span>
                                                </button>
                                            </li>
                                        </template>
                                    </ul>
                                    <p x-show="filteredCountries.length === 0" class="px-4 pb-3 text-sm text-muted">{{ __('tourism.request.destination_no_results') }}</p>
                                </div>
                            </div>

                            <div x-show="typicalPrices[destination]" x-cloak class="mt-2 flex items-center gap-1.5 rounded-lg bg-primary/5 px-3 py-2 text-xs text-ink">
                                <span>💡 {{ __('tourism.request.typical_price_label') }}</span>
                                {{-- Uses the same formatBudget() helper as the budget slider, for consistent currency and sizing. --}}
                                <span
                                    class="text-sm font-semibold text-primary"
                                    x-text="typicalPrices[destination] ? formatBudget(typicalPrices[destination]) : ''"
                                ></span>
                            </div>
                        </div>

                        <div class="mt-5">
                            <x-form-input
                                name="hotel_name"
                                :label="__('tourism.request.hotel_name')"
                                :placeholder="__('tourism.request.hotel_name_placeholder')"
                            />
                        </div>

                        {{-- grid-cols-1 below sm - the native date picker's placeholder
                        text and calendar icon crowd each other once a column drops
                        below ~155px, which happens on phone widths inside this
                        max-w-2xl form. --}}
                        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-form-input type="date" name="check_in" :label="__('tourism.request.check_in')" x-model="checkIn" required />
                            <x-form-input type="date" name="check_out" :label="__('tourism.request.check_out')" x-model="checkOut" required />
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <x-form-input type="number" name="adults" :label="__('tourism.request.adults')" x-model.number="adults" min="1" max="20" required />
                            <x-form-input type="number" name="children" :label="__('tourism.request.children')" x-model.number="children" min="0" max="20" />
                        </div>
                    </div>

                    {{-- 2. Preferences --}}
                    <div class="border-t border-placeholder pt-6">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-accent-yellow/20 font-heading text-sm font-bold text-ink">2</span>
                            <p class="text-sm font-semibold text-ink">{{ __('tourism.request.section_preferences') }}</p>
                        </div>

                        <div class="mt-4 flex flex-wrap gap-2">
                            <label class="group cursor-pointer">
                                <input type="checkbox" name="all_inclusive" value="1" x-model="allInclusive" class="peer sr-only">
                                <span class="flex items-center gap-1.5 rounded-full border border-border-muted px-3 py-1.5 text-sm text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:text-primary group-hover:border-primary/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 opacity-0 transition-opacity peer-checked:opacity-100">
                                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ __('tourism.request.all_inclusive') }}
                                </span>
                            </label>
                            <label class="group cursor-pointer">
                                <input type="checkbox" name="insurance" value="1" x-model="insurance" class="peer sr-only">
                                <span class="flex items-center gap-1.5 rounded-full border border-border-muted px-3 py-1.5 text-sm text-ink transition peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:text-primary group-hover:border-primary/60">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 shrink-0 opacity-0 transition-opacity peer-checked:opacity-100">
                                        <path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" />
                                    </svg>
                                    {{ __('tourism.request.insurance') }}
                                </span>
                            </label>
                        </div>
                    </div>

                    {{-- 3. Budget --}}
                    <div class="border-t border-placeholder pt-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slide-blue/30 font-heading text-sm font-bold text-ink">3</span>
                                <p class="text-sm font-semibold text-ink">{{ __('tourism.request.section_budget') }}</p>
                            </div>

                            {{-- Display-only (see formatBudget()) - the slider still drags in AMD underneath regardless of the currency shown here. --}}
                            <label class="flex items-center gap-1.5 text-xs text-muted">
                                {{ __('tourism.request.budget_currency_label') }}
                                <select x-model="budgetCurrency" class="rounded-md border border-border-muted bg-white px-2 py-1 text-xs font-medium text-ink focus:border-primary focus:outline-none">
                                    <template x-for="code in Object.keys(currencyRates)" :key="code">
                                        <option :value="code" x-text="code" :selected="code === budgetCurrency"></option>
                                    </template>
                                </select>
                            </label>
                        </div>
                        <p class="mt-1 text-xs text-muted">{{ __('tourism.request.budget_hint') }}</p>

                        {{-- Two overlapping range inputs, pointer-events re-enabled only on the thumb (standard dual-range-slider trick). Untouched = no budget filter submitted; max dragged to the cap = "no upper limit" (max hidden input disabled) rather than a hard ceiling. --}}
                        <div class="mt-5">
                            <div class="relative py-2">
                                <div class="relative h-1.5 rounded-full bg-placeholder/50">
                                    <div
                                        class="absolute inset-y-0 rounded-full bg-primary"
                                        :style="`left: ${(budgetMin / budgetSliderMax) * 100}%; right: ${100 - (budgetMax / budgetSliderMax) * 100}%`"
                                    ></div>
                                </div>
                                <input
                                    type="range"
                                    min="0"
                                    :max="budgetSliderMax"
                                    step="25000"
                                    x-model.number="budgetMin"
                                    @input="budgetTouched = true; if (budgetMin > budgetMax) budgetMax = budgetMin"
                                    class="pointer-events-none absolute inset-x-0 top-0 h-1.5 w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-[18px] [&::-moz-range-thumb]:w-[18px] [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-[3px] [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-[18px] [&::-webkit-slider-thumb]:w-[18px] [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-[3px] [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-md"
                                >
                                <input
                                    type="range"
                                    min="0"
                                    :max="budgetSliderMax"
                                    step="25000"
                                    x-model.number="budgetMax"
                                    @input="budgetTouched = true; if (budgetMax < budgetMin) budgetMin = budgetMax"
                                    class="pointer-events-none absolute inset-x-0 top-0 h-1.5 w-full cursor-pointer appearance-none bg-transparent [&::-moz-range-thumb]:pointer-events-auto [&::-moz-range-thumb]:h-[18px] [&::-moz-range-thumb]:w-[18px] [&::-moz-range-thumb]:appearance-none [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-[3px] [&::-moz-range-thumb]:border-white [&::-moz-range-thumb]:bg-primary [&::-moz-range-thumb]:shadow-md [&::-webkit-slider-thumb]:pointer-events-auto [&::-webkit-slider-thumb]:h-[18px] [&::-webkit-slider-thumb]:w-[18px] [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:border-[3px] [&::-webkit-slider-thumb]:border-white [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-md"
                                >
                            </div>
                            {{-- Same treatment as the "typical price" figure above, for consistent sizing between highlighted-amount callouts. --}}
                            <div class="mt-1 flex items-center justify-between text-sm font-semibold text-primary">
                                <span x-text="formatBudget(budgetMin)"></span>
                                <span x-text="budgetMax >= budgetSliderMax ? formatBudget(budgetSliderMax) + '+' : formatBudget(budgetMax)"></span>
                            </div>

                            <input type="hidden" name="budget_min_amd" x-model.number="budgetMin" :disabled="!budgetTouched">
                            <input type="hidden" name="budget_max_amd" x-model.number="budgetMax" :disabled="!budgetTouched || budgetMax >= budgetSliderMax">
                        </div>
                    </div>

                    {{-- 4. Notes --}}
                    <div class="border-t border-placeholder pt-6">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slide-green/20 font-heading text-sm font-bold text-ink">4</span>
                            <p class="text-sm font-semibold text-ink">{{ __('tourism.request.section_notes') }}</p>
                        </div>

                        <div class="mt-4">
                            <textarea
                                name="notes"
                                id="notes"
                                rows="3"
                                placeholder="{{ __('tourism.request.notes_placeholder') }}"
                                class="block w-full rounded-md border px-3 py-2 text-sm text-ink focus:outline-none {{ $errors->has('notes') ? 'border-red-400 focus:border-red-500' : 'border-border-muted focus:border-primary' }}"
                            >{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @guest
                        {{-- 5. Your details --}}
                        <div class="border-t border-placeholder pt-6">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slide-blue/30 font-heading text-sm font-bold text-ink">5</span>
                                <p class="text-sm font-semibold text-ink">{{ __('tourism.request.section_details') }}</p>
                            </div>

                            <div class="mt-4 space-y-4">
                                <x-form-input name="guest_name" :label="__('tourism.request.your_name')" required />

                                <div>
                                    <x-form-input type="email" name="guest_email" :label="__('tourism.request.your_email')" required />
                                    <p class="mt-1.5 text-xs text-subtle">
                                        {{ __('tourism.request.your_email_hint') }}
                                        <a href="{{ route('tourism.resend') }}" class="text-primary hover:underline">{{ __('tourism.resend.heading') }}</a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endguest
                </div>
            </div>
                </div>

                {{-- Sticky on large screens; single stacked column (not 2-up) since this sidebar is a fixed ~380px regardless of viewport width. --}}
                <div class="lg:sticky lg:top-24">
                    <div class="rounded-2xl border-2 border-primary/20 bg-primary/5 p-5 sm:p-6">
                        <p class="text-xs font-semibold tracking-wider text-subtle uppercase">{{ __('tourism.request.summary_heading') }}</p>
                        <dl class="mt-3 space-y-3">
                            <div>
                                <dt class="text-xs text-muted">{{ __('tourism.request.summary_destination') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink">
                                    <template x-if="selectedCountry">
                                        <span x-text="selectedCountry.flag + ' ' + selectedCountry.name"></span>
                                    </template>
                                    <span x-show="!selectedCountry" class="font-normal text-subtle">{{ __('tourism.request.summary_not_set') }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">{{ __('tourism.request.summary_dates') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink">
                                    <template x-if="checkIn && checkOut">
                                        <span x-text="checkIn + ' → ' + checkOut + (nights ? ' (' + nights + ' {{ __('tourism.request.summary_nights') }})' : '')"></span>
                                    </template>
                                    <span x-show="!(checkIn && checkOut)" class="font-normal text-subtle">{{ __('tourism.request.summary_not_set') }}</span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">{{ __('tourism.request.summary_travelers') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink" x-text="travelersLabel"></dd>
                            </div>
                            <div>
                                <dt class="text-xs text-muted">{{ __('tourism.request.summary_budget') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-ink">
                                    <span x-show="budgetTouched" x-text="budgetLabel"></span>
                                    <span x-show="!budgetTouched" class="font-normal text-subtle">{{ __('tourism.request.summary_not_set') }}</span>
                                </dd>
                            </div>
                        </dl>
                        <div x-show="allInclusive || insurance" x-cloak class="mt-3 flex flex-wrap gap-1.5">
                            <span x-show="allInclusive" class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-primary ring-1 ring-primary/30">{{ __('tourism.request.all_inclusive') }}</span>
                            <span x-show="insurance" class="rounded-full bg-white px-2.5 py-1 text-xs font-medium text-primary ring-1 ring-primary/30">{{ __('tourism.request.insurance') }}</span>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-placeholder bg-white p-6 shadow-sm">
                        <label class="flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="consent" value="1" required class="mt-0.5 rounded border-border-muted text-primary focus:ring-primary">
                            <span>{{ __('tourism.request.consent') }}</span>
                        </label>
                        @error('consent')
                            <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
                        @enderror

                        <button
                            type="submit"
                            class="mt-6 w-full bg-primary px-6 py-3 text-sm font-medium text-white shadow-sm transition duration-300 hover:-translate-y-0.5 hover:bg-primary-dark hover:shadow-md"
                        >
                            {{ __('tourism.request.submit') }}
                        </button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Standalone, outside the main form - a nested <form> is invalid HTML and gets silently dropped on parse. The visible "notify me" controls above target this one via form="" instead. --}}
        @error('destination_country')
            <form id="notify-me-form" method="POST" action="{{ route('tourism.destination-alerts.store') }}" class="hidden">
                @csrf
                <input type="hidden" name="destination_country" value="{{ old('destination_country') }}">
            </form>
        @enderror
    </section>
@endsection
