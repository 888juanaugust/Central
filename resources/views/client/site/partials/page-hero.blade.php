{{-- The heading band of an inner page: the home's dark card, shorter, with one generic drawing beside the words. --}}
<section class="site-page-hero site-page-hero--band">
    <h1 class="site-page-hero__title">{{ $title }}</h1>
    @if (! empty($lede))
        <p class="site-page-hero__lede">{{ $lede }}</p>
    @endif
    @include('client.site.partials.drawings.'.$drawing, ['class' => 'site-page-hero__drawing'])
</section>
