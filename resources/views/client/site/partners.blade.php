@extends('client.site.layout')

@php
    use App\Client\Site\Copy;

    // A partner's tile: the first letters of its first two words, until partners carry logos.
    $initials = fn (string $name): string => collect(preg_split('/\s+/u', trim($name)))
        ->filter(fn (string $word): bool => preg_match('/^\p{L}/u', $word) === 1)
        ->take(2)
        ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');
@endphp

@section('title', __('Partners'))
@section('description', __('The suppliers, distributors and logistics partners :name works with.', ['name' => Copy::shortName()]))

@section('content')

    @include('client.site.partials.page-hero', ['title' => __('Partners'), 'lede' => __('The companies we build our supply and our reach with.'), 'drawing' => 'suspension'])

    <section class="site-section site-section--top">
        <ul class="site-partners">
            @forelse ($partners as $partner)
                <li class="site-partner">
                    <span class="site-partner__tile" aria-hidden="true">{{ $initials($partner['name']) }}</span>
                    <h2 class="site-partner__name">{{ $partner['name'] }}</h2>
                    <p class="site-partner__text">{{ $partner['description'] }}</p>
                    <p class="site-partner__meta">
                        <span class="site-partner__field">{{ $partner['field'] }}</span>
                        @if (! empty($partner['country']))
                            <span>{{ $partner['country'] }}</span>
                        @endif
                        @if (! empty($partner['since']))
                            <span>{{ __('Since :year', ['year' => $partner['since']]) }}</span>
                        @endif
                    </p>
                </li>
            @empty
                <li class="site-partners__empty">{{ __('No partners listed yet.') }}</li>
            @endforelse
        </ul>
    </section>

    @include('client.site.partials.closing-band', [
        'title' => __('Work with us'),
        'lede' => __('Suppliers and regional distributors who want to carry or supply our brands are welcome to get in touch.'),
        'action' => __('Contact us'),
    ])

@endsection
