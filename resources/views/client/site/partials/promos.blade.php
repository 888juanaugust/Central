{{--
    The carousel: the Owner's live promos, in their order. A promo is an Instagram post, shown whole on the tile
    tint and never cropped; its words stand beside it as text, because the words inside an image cannot be
    translated or read out. One slide needs no script; several rotate from site.js.
--}}
@php
    use App\Client\Site\Copy;
    use App\Domain\Shared\Format;
@endphp
<section class="site-section">
    <div class="site-promos site-promos--posters" data-carousel aria-roledescription="carousel" aria-label="{{ __('Promotions') }}">
        <div class="site-promos__frame">
            @if (count($promos) > 1)
                @include('client.site.partials.carousel-arrow', ['direction' => 'prev'])
            @endif
            <div class="site-promos__track">
                @foreach ($promos as $i => $promo)
                    @php
                        $title = (string) Copy::pick($promo->title);
                        $picked = Copy::pick($promo->text ?? []);
                        $text = is_string($picked) ? $picked : ''; // a promo without text
                        $link = $promo->safeLink();
                    @endphp
                    <article @class(['site-promo', 'site-promo--poster', 'is-active' => $i === 0]) data-slide aria-hidden="{{ $i === 0 ? 'false' : 'true' }}" aria-roledescription="slide" aria-label="{{ $title }}">
                        <div class="site-promo__plate">
                            <img class="site-promo__poster" src="{{ $promo->url() }}" alt="{{ $title }}" width="1080" height="1350" @if ($i > 0) loading="lazy" @endif>
                        </div>
                        <div class="site-promo__body">
                            <h2 class="site-promo__title">{{ $title }}</h2>
                            @if ($text !== '')
                                <p class="site-promo__text">{{ $text }}</p>
                            @endif
                            @if ($promo->show_until)
                                <p class="site-promo__until">{{ __('Valid until :date', ['date' => Format::date($promo->show_until)]) }}</p>
                            @endif
                            @if ($link)
                                <a href="{{ $link }}" class="site-link">{{ __('See the offer') }} @include('client.site.partials.arrow')</a>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>
            @if (count($promos) > 1)
                @include('client.site.partials.carousel-arrow', ['direction' => 'next'])
            @endif
        </div>
        @if (count($promos) > 1)
            <div class="site-promos__dots-row">
                <div class="site-promos__dots">
                    @foreach ($promos as $i => $promo)
                        <button type="button" class="site-promos__dot" data-dot aria-current="{{ $i === 0 ? 'true' : 'false' }}" aria-label="{{ __('Slide :number', ['number' => $i + 1]) }}"></button>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</section>
