{{-- The floating way to ask a question, on every page; gone when no WhatsApp number is set. --}}
@if ($whatsapp)
    <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="site-whatsapp" aria-label="{{ __('Chat with us on WhatsApp') }}">
        @include('client.site.partials.whatsapp-icon')
        <span class="site-whatsapp__label">{{ __('WhatsApp') }}</span>
    </a>
@endif
