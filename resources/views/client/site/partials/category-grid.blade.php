{{-- The categories as cards, while promos hold the carousel: a wide and narrow rhythm rather than a row of equal cards. --}}
<section class="site-section">
    @include('client.site.partials.category-head')
    <div class="site-categories">
        @foreach ($categories as $i => $category)
            <article @class(['site-category', 'site-category--wide' => in_array($i % 4, [0, 3], true)])>
                @include('client.site.partials.category-drawing', ['name' => $category['name'], 'class' => 'site-category__drawing'])
                <div class="site-category__copy">
                    <h3 class="site-category__name">{{ $category['name'] }}</h3>
                    <p class="site-category__text">{{ $category['description'] }}</p>
                </div>
            </article>
        @endforeach
    </div>
    <p class="site-note">{{ __('The full list with your own prices is in the customer portal after sign-in.') }}</p>
</section>
