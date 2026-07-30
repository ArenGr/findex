@props(['article' => null, 'languages'])

<x-form-input name="title" :label="__('writer.articles.title_label')" :value="old('title', $article->title ?? '')" required />

<div>
    <label for="language" class="block text-sm font-medium text-ink">{{ __('writer.articles.language_label') }}</label>
    <select
        name="language"
        id="language"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >
        @foreach ($languages as $code => $meta)
            <option value="{{ $code }}" @selected(old('language', $article->language ?? null) === $code)>{{ $meta['native'] }}</option>
        @endforeach
    </select>
    @error('language')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="excerpt" class="block text-sm font-medium text-ink">{{ __('writer.articles.excerpt_label') }}</label>
    <textarea
        name="excerpt"
        id="excerpt"
        rows="3"
        maxlength="500"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >{{ old('excerpt', $article->excerpt ?? '') }}</textarea>
    @error('excerpt')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="featured_image" class="block text-sm font-medium text-ink">{{ __('writer.articles.featured_image_label') }}</label>
    @if (($article->featured_image_url ?? null))
        <img src="{{ $article->featured_image_url }}" alt="" class="mt-2 h-28 w-full rounded-md object-cover">
    @endif
    <input
        type="file"
        name="featured_image"
        id="featured_image"
        accept="image/*"
        class="mt-1.5 block w-full text-sm text-ink file:mr-3 file:border-0 file:bg-primary/10 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-primary"
    >
    @error('featured_image')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<div>
    <label for="body" class="block text-sm font-medium text-ink">{{ __('writer.articles.body_label') }}</label>
    <textarea
        name="body"
        id="body"
        rows="16"
        class="mt-1.5 block w-full rounded-md border border-border-muted px-3 py-2 text-sm text-ink focus:border-primary focus:outline-none"
    >{{ old('body', $article->body ?? '') }}</textarea>
    @error('body')
        <p class="mt-1.5 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
