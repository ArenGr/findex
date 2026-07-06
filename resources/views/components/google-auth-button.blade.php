<div class="mt-6 flex items-center gap-3 text-xs text-subtle uppercase">
    <span class="h-px flex-1 bg-placeholder"></span>
    {{ __('auth.or_divider') }}
    <span class="h-px flex-1 bg-placeholder"></span>
</div>

<a
    href="{{ route('auth.google.redirect') }}"
    class="mt-6 flex w-full items-center justify-center gap-3 rounded-md border border-border-muted px-6 py-3 text-sm font-medium text-ink transition hover:bg-placeholder/20"
>
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="h-5 w-5">
        <path fill="#4285F4" d="M23.49 12.27c0-.79-.07-1.54-.19-2.27H12v4.51h6.47c-.29 1.48-1.14 2.73-2.4 3.58v2.98h3.89c2.28-2.1 3.53-5.2 3.53-8.8z"/>
        <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.89-2.98c-1.08.72-2.45 1.16-4.04 1.16-3.11 0-5.74-2.1-6.68-4.92H1.3v2.99C3.26 21.3 7.31 24 12 24z"/>
        <path fill="#FBBC05" d="M5.32 14.35c-.24-.72-.38-1.49-.38-2.35s.14-1.63.38-2.35V6.66H1.3A11.95 11.95 0 0 0 0 12c0 1.93.46 3.76 1.3 5.34l4.02-2.99z"/>
        <path fill="#EA4335" d="M12 4.75c1.76 0 3.34.6 4.59 1.79l3.44-3.44C17.94 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.3 6.66l4.02 2.99c.94-2.82 3.57-4.9 6.68-4.9z"/>
    </svg>
    {{ __('auth.continue_with_google') }}
</a>
