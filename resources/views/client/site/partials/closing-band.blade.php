{{-- The closing action: flat accent, the one blue surface on a page, a bearing behind. --}}
<section class="site-section">
    <div class="site-cta">
        @include('client.site.partials.drawings.hero-bearing', ['class' => 'site-cta__drawing', 'spin' => 'site-cta__spin'])
        <div>
            <h2 class="site-cta__title">{{ $title }}</h2>
            <p class="site-cta__lede">{{ $lede }}</p>
        </div>
        <div class="site-cta__actions">
            <a href="{{ route('site.contact') }}" class="site-btn site-btn--on-accent site-btn--lg">{{ $action }}</a>
            @if ($signIn ?? false)
                <a href="{{ route('site.sign_in') }}" class="site-cta__link">{{ __('Already a customer? Sign in') }}</a>
            @endif
        </div>
    </div>
</section>
