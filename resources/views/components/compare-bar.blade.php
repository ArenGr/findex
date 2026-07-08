<div
    x-data
    x-show="$store.compare.items.length > 0"
    x-cloak
    x-transition
    class="fixed inset-x-0 bottom-0 z-30 border-t border-placeholder bg-white px-6 py-4 shadow-lg"
>
    <div class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-4">
        <div class="flex flex-wrap items-center gap-2">
            <template x-for="item in $store.compare.items" :key="item.slug">
                <span class="flex items-center gap-2 rounded-full bg-placeholder/40 py-1 pl-1 pr-3">
                    <img x-show="item.logo" :src="item.logo" alt="" class="h-6 w-6 rounded-full object-contain">
                    <span class="text-xs font-medium text-ink" x-text="item.name"></span>
                    <button type="button" @click="$store.compare.remove(item.slug)" class="text-subtle hover:text-ink" aria-label="{{ __('organizations.compare_clear') }}">
                        &times;
                    </button>
                </span>
            </template>
        </div>

        <div class="flex shrink-0 items-center gap-4">
            <button type="button" @click="$store.compare.clear()" class="text-xs text-muted hover:text-ink">
                {{ __('organizations.compare_clear') }}
            </button>

            <span x-show="$store.compare.items.length < 2" class="text-xs text-subtle">
                {{ __('organizations.compare_need_more') }}
            </span>

            <a
                x-show="$store.compare.items.length >= 2"
                :href="'{{ route('organizations.compare') }}?orgs=' + $store.compare.items.map((item) => item.slug).join(',')"
                class="bg-primary px-6 py-2.5 text-sm font-medium text-white hover:bg-primary-dark"
            >
                {{ __('organizations.compare_now') }} (<span x-text="$store.compare.items.length"></span>)
            </a>
        </div>
    </div>
</div>
