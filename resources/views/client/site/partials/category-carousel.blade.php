{{--
    The carousel while no promo is live: one slide per category, under a strip built like the workspace's tabs, so
    every category stays named while one is shown. Rotates from site.js, as the promos do.
--}}
<section class="site-section">
    @include('client.site.partials.category-head')
    <div class="site-promos site-promos--categories" data-carousel aria-roledescription="carousel" aria-label="{{ __('Product categories') }}">
        @if (count($categories) > 1)
            <div class="site-promos__strip">
                @foreach ($categories as $i => $category)
                    <button type="button" class="site-promos__tab" data-dot aria-current="{{ $i === 0 ? 'true' : 'false' }}">{{ $category['name'] }}</button>
                @endforeach
            </div>
        @endif
        <div class="site-promos__frame">
            @if (count($categories) > 1)
                @include('client.site.partials.carousel-arrow', ['direction' => 'prev'])
            @endif
            <div class="site-promos__track">
                @foreach ($categories as $i => $category)
                    <article @class(['site-promo', 'site-promo--category', 'is-active' => $i === 0]) data-slide aria-hidden="{{ $i === 0 ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $category['name'] }}">
                        <div class="site-promo__body">
                            <h3 class="site-promo__title">{{ $category['name'] }}</h3>
                            <p class="site-promo__text">{{ $category['description'] }}</p>
                            <a href="{{ route('site.contact') }}" class="site-link">{{ __('Contact us') }} @include('client.site.partials.arrow')</a>
                        </div>
                        <div class="site-promo__plate">
                            @include('client.site.partials.category-drawing', ['name' => $category['name'], 'class' => 'site-promo__drawing'])
                        </div>
                    </article>
                @endforeach
            </div>
            @if (count($categories) > 1)
                @include('client.site.partials.carousel-arrow', ['direction' => 'next'])
            @endif
        </div>
    </div>
    <p class="site-note">{{ __('The full list with your own prices is in the customer portal after sign-in.') }}</p>
</section>
