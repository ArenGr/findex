{{-- The voice concierge, hidden behind services.openai.voice_fill. --}}
@if (config('services.openai.voice_fill'))
    <div class="rounded-xl border border-travel-200 bg-travel-50/70 p-6">
        <div class="flex items-start gap-3">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-travel-600 text-white">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5 fill-none stroke-current" stroke-width="1.8">
                    <rect x="9" y="2" width="6" height="12" rx="3" stroke-linecap="round" stroke-linejoin="round" />
                    <path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-bold text-gray-900">{{ __('tourism.request.voice_fill_card_heading') }}</p>
                <p class="text-xs text-gray-500">{{ __('tourism.request.voice_fill_hint') }}</p>
                <p class="mt-1 text-xs text-gray-400 italic">{{ __('tourism.request.voice_fill_example') }}</p>

                <div class="mt-4">
                    <template x-if="state === 'idle'">
                        <button type="button" @click="start()" class="flex items-center gap-2 rounded-full bg-travel-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-travel-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-none stroke-current" stroke-width="2">
                                <rect x="9" y="2" width="6" height="12" rx="3" stroke-linecap="round" stroke-linejoin="round" />
                                <path d="M5 11a7 7 0 0 0 14 0M12 18v3" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            {{ __('tourism.request.voice_fill_start') }}
                        </button>
                    </template>

                    <template x-if="state === 'recording'">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-2 text-xs text-gray-700">
                                <span class="h-2.5 w-2.5 animate-pulse rounded-full bg-error"></span>
                                <span>{{ __('tourism.request.voice_fill_recording') }}</span>
                                <span class="text-gray-400 tabular-nums" x-text="formattedTime"></span>
                                <span class="flex h-3 items-end gap-0.5" aria-hidden="true">
                                    <span class="w-1 rounded-full bg-travel-600 transition-all" :style="`height: ${Math.max(15, level * 100)}%`"></span>
                                    <span class="w-1 rounded-full bg-travel-600 transition-all" :style="`height: ${Math.max(15, level * 75)}%`"></span>
                                    <span class="w-1 rounded-full bg-travel-600 transition-all" :style="`height: ${Math.max(15, level * 100)}%`"></span>
                                </span>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" @click="cancel()" class="rounded-full border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-500 hover:bg-white">
                                    {{ __('tourism.request.voice_fill_cancel') }}
                                </button>
                                <button type="button" @click="stop()" class="rounded-full bg-travel-600 px-4 py-1.5 text-xs font-bold text-white hover:bg-travel-700">
                                    {{ __('tourism.request.voice_fill_stop') }}
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="state === 'processing'">
                        <div class="flex items-center gap-2 text-xs text-gray-700">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-4 w-4 shrink-0 animate-spin text-travel-600">
                                <circle cx="12" cy="12" r="9" class="opacity-25" stroke="currentColor" stroke-width="3" fill="none" />
                                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round" fill="none" />
                            </svg>
                            <span>{{ __('tourism.request.voice_fill_processing') }}</span>
                        </div>
                    </template>

                    <template x-if="state === 'error'">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <p class="text-xs text-error" x-text="error"></p>
                            <button type="button" @click="state = 'idle'; error = ''" class="shrink-0 rounded-full border border-error/40 px-3 py-1.5 text-xs font-semibold text-error hover:bg-error/5">
                                {{ __('tourism.request.voice_fill_retry') }}
                            </button>
                        </div>
                    </template>

                    <div x-show="applied === 'applied'" x-cloak x-transition class="flex flex-wrap items-center justify-between gap-2">
                        <p class="flex items-center gap-1.5 text-xs font-bold text-travel-700">
                            <x-travel-icon name="check" class="h-4 w-4" />
                            {{ __('tourism.request.voice_fill_applied') }}
                        </p>
                        <button type="button" @click="clearVoiceFields()" class="shrink-0 rounded-full border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500 hover:bg-white">
                            {{ __('tourism.request.voice_fill_clear') }}
                        </button>
                    </div>

                    <p x-show="applied === 'empty'" x-cloak x-transition class="text-xs font-semibold text-amber-700">
                        {{ __('tourism.request.voice_fill_nothing_found') }}
                    </p>
                </div>
            </div>
        </div>
    </div>
@endif
