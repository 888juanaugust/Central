@extends('client.site.layout')

@php
    use App\Client\Site\Copy;

    $whatsapp = Copy::whatsapp();
@endphp

@section('description', Copy::text('summary'))

@section('content')

    {{-- The company in one card: the workspace's rail colour, one statement, two actions, and a bearing that turns as the page scrolls. --}}
    <section class="site-hero">
        <div class="site-hero__body">
            <h1 class="site-hero__title">{{ Copy::text('tagline') }}</h1>
            <p class="site-hero__lede">{{ Copy::text('summary') }}</p>
            <div class="site-hero__actions">
                <a href="{{ route('site.contact') }}" class="site-btn site-btn--primary site-btn--lg">{{ __('Become a customer') }}</a>
                <a href="{{ route('site.sign_in') }}" class="site-btn site-btn--secondary site-btn--lg">{{ __('Sign in to your account') }}</a>
            </div>
            @if ($whatsapp)
                <p class="site-hero__whatsapp">
                    <a href="{{ $whatsapp }}" target="_blank" rel="noopener">@include('client.site.partials.whatsapp-icon')<span>{{ __('Or message us on WhatsApp') }}</span></a>
                </p>
            @endif
        </div>
        @include('client.site.partials.drawings.hero-bearing', ['class' => 'site-hero__drawing', 'spin' => 'site-hero__spin'])
    </section>

    @include('client.site.partials.glance')

    {{-- One slot under the hero. Live promos take it and the categories stand as cards; with none, the categories take it. A category never shows twice. --}}
    @if ($promos->isNotEmpty())
        @include('client.site.partials.promos')
        @includeWhen(count($categories) > 0, 'client.site.partials.category-grid')
    @else
        @includeWhen(count($categories) > 0, 'client.site.partials.category-carousel')
    @endif

    @include('client.site.partials.portal-features')

    {{-- Who buys from us, and how an account opens: the distance between interested and ordering. --}}
    <section class="site-section">
        <div class="site-two-col">
            <div>
                <h2 class="site-h2">{{ __('Who we serve') }}</h2>
                <dl class="site-dl">
                    @foreach (Copy::records('serves') as $segment)
                        <div class="site-dl__row">
                            <dt>{{ $segment['title'] }}</dt>
                            <dd>{{ $segment['text'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
            <div>
                <h2 class="site-h2">{{ __('Become a customer in three steps') }}</h2>
                <ol class="site-steps">
                    @foreach (Copy::records('steps') as $i => $step)
                        <li class="site-step">
                            <span class="site-step__number" aria-hidden="true">{{ $i + 1 }}</span>
                            <div>
                                <h3 class="site-step__title">{{ $step['title'] }}</h3>
                                <p class="site-step__text">{{ $step['text'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                <a href="{{ route('site.contact') }}" class="site-link">{{ __('Contact us') }} @include('client.site.partials.arrow')</a>
            </div>
        </div>
    </section>

    @includeWhen($photos->isNotEmpty(), 'client.site.partials.photos')

    {{-- About, with the partners condensed beside it. --}}
    <section class="site-section">
        <div class="site-two-col">
            <div>
                <h2 class="site-h2">{{ __('About the company') }}</h2>
                @foreach (array_slice(Copy::list('profile'), 0, 2) as $paragraph)
                    <p class="site-p">{{ $paragraph }}</p>
                @endforeach
                <a href="{{ route('site.about') }}" class="site-link">{{ __('More about us') }} @include('client.site.partials.arrow')</a>
            </div>
            <div>
                <div class="site-row-head">
                    <h2 class="site-h2">{{ __('Our partners') }}</h2>
                    <a href="{{ route('site.partners') }}" class="site-link site-link--sm">{{ __('All partners') }} @include('client.site.partials.arrow')</a>
                </div>
                <ul class="site-list">
                    @foreach ($partners as $partner)
                        <li class="site-list__row site-list__row--partner">
                            <div>
                                <p class="site-list__title">{{ $partner['name'] }}</p>
                                <p class="site-list__text">{{ $partner['description'] }}</p>
                            </div>
                            <p class="site-list__meta">{{ $partner['field'] }}</p>
                            @if (! empty($partner['since']))
                                <p class="site-list__meta site-list__meta--end">{{ __('Since :year', ['year' => $partner['since']]) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    @includeWhen($faq !== [], 'client.site.partials.faq')

    @include('client.site.partials.closing-band', [
        'title' => __('Open a wholesale account'),
        'lede' => __('Send your business name, city and the kind of parts you need.'),
        'action' => __('Become a customer'),
        'signIn' => true,
    ])

@endsection
