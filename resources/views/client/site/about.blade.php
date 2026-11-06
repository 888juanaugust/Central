@extends('client.site.layout')

@php use App\Client\Site\Copy; @endphp

@section('title', __('About us'))
@section('description', __(':name: who we are, who we serve and how an account opens.', ['name' => Copy::shortName()]))

@section('content')

    @include('client.site.partials.page-hero', ['title' => __('About us'), 'lede' => Copy::text('tagline'), 'drawing' => 'hydraulic'])

    {{-- The profile and the company's details share one card: two real halves, nothing placed to fill a corner. --}}
    <section class="site-section">
        <div class="site-profile">
            <div class="site-profile__text">
                <h2 class="site-h2">{{ __('Company profile') }}</h2>
                @foreach (Copy::list('profile') as $paragraph)
                    <p class="site-p">{{ $paragraph }}</p>
                @endforeach
            </div>
            <dl class="site-profile__facts" aria-label="{{ __('Company details') }}">
                <div><dt>{{ __('Legal name') }}</dt><dd>{{ Copy::name() }}</dd></div>
                <div><dt>{{ __('Entity') }}</dt><dd>{{ Copy::value('legal.entity') }}</dd></div>
                @if (Copy::value('legal.nib'))
                    <div><dt>{{ __('NIB') }}</dt><dd>{{ Copy::value('legal.nib') }}</dd></div>
                @endif
                @if (Copy::value('legal.established'))
                    <div><dt>{{ __('Established') }}</dt><dd>{{ Copy::value('legal.established') }}</dd></div>
                @endif
                <div><dt>{{ __('Head office') }}</dt><dd>{{ Copy::value('contact.city') }}</dd></div>
                <div><dt>{{ __('Brands') }}</dt><dd>{{ implode(', ', Copy::list('brands')) }}</dd></div>
            </dl>
        </div>
    </section>

    @if ($categories !== [])
        <section class="site-section">
            <h2 class="site-h2">{{ __('Product categories') }}</h2>
            <div class="site-plates">
                @foreach ($categories as $category)
                    <article class="site-plate">
                        <div class="site-plate__art">
                            @include('client.site.partials.category-drawing', ['name' => $category['name'], 'class' => 'site-plate__drawing'])
                        </div>
                        <h3 class="site-plate__name">{{ $category['name'] }}</h3>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    <section class="site-section">
        <h2 class="site-h2">{{ __('Who we serve') }}</h2>
        <div class="site-cells">
            @foreach (Copy::records('serves') as $segment)
                <div class="site-cell">
                    <h3 class="site-cell__title">{{ $segment['title'] }}</h3>
                    <p class="site-cell__text">{{ $segment['text'] }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- The steps stand on one line: their order is the point. --}}
    <section class="site-section">
        <h2 class="site-h2">{{ __('How an account opens') }}</h2>
        <ol class="site-path">
            @foreach (Copy::records('steps') as $i => $step)
                <li class="site-path__step">
                    <span class="site-path__number" aria-hidden="true">{{ $i + 1 }}</span>
                    <h3 class="site-path__title">{{ $step['title'] }}</h3>
                    <p class="site-path__text">{{ $step['text'] }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    @include('client.site.partials.closing-band', [
        'title' => __('Open a wholesale account'),
        'lede' => __('Send your business name, city and the kind of parts you need.'),
        'action' => __('Become a customer'),
        'signIn' => true,
    ])

@endsection
