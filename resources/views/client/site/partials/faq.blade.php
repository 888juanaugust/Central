{{-- The questions a buyer asks before opening an account, on one split card: the heading and the way to ask on the tint, the list on white. <details> opens without a script, and the first stands open so the section shows an answer; the numbers come from the system. --}}
<section class="site-section">
    <div class="site-faq">
        <div class="site-faq__head">
            <h2 class="site-h2">{{ __('Frequently asked questions') }}</h2>
            <p class="site-lede">{{ __('What workshops and shops ask us before they open an account.') }}</p>
            @if ($whatsapp)
                <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="site-btn site-btn--primary">{{ __('Another question? Ask us on WhatsApp') }}</a>
            @else
                <a href="{{ route('site.contact') }}" class="site-btn site-btn--primary">{{ __('Another question? Contact us') }}</a>
            @endif
            @include('client.site.partials.drawings.hero-bearing', ['class' => 'site-faq__drawing', 'spin' => 'site-faq__spin'])
        </div>
        <div class="site-faq__list">
            @foreach ($faq as $item)
                <details class="site-faq__item" @if ($loop->first) open @endif>
                    <summary class="site-faq__question">
                        <span>{{ $item['question'] }}</span>
                        <svg class="site-faq__toggle" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M10 4.5v11M4.5 10h11"/></svg>
                    </summary>
                    <div class="site-faq__answer">
                        <p>{{ $item['answer'] }}</p>
                    </div>
                </details>
            @endforeach
        </div>
    </div>
</section>

@push('schema')
    {{-- Raw PHP tags on purpose: Blade would read schema.org's "@context" as a directive. --}}
    <script type="application/ld+json"><?php echo json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn (array $item) => [
            '@type' => 'Question',
            'name' => $item['question'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
        ], $faq),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?></script>
@endpush
