{{-- The company at a glance: the mark beside the name, and the facts a buyer sizes a supplier up by, read from the system. --}}
<div class="site-glance" data-reveal>
    <div class="site-glance__identity">
        <img src="{{ asset(config('client.theme.logo')) }}" alt="" aria-hidden="true" class="site-glance__mark site-mark--light">
        <img src="{{ asset(config('client.theme.logo_dark') ?: config('client.theme.logo')) }}" alt="" aria-hidden="true" class="site-glance__mark site-mark--dark">
        <div>
            <p class="site-glance__name">{{ \App\Client\Site\Copy::shortName() }}</p>
            <p class="site-glance__tagline">{{ \App\Client\Site\Copy::text('tagline') }}</p>
            <p class="site-glance__serves">{{ __('Serving workshops, parts shops and distributors across Indonesia.') }}</p>
        </div>
    </div>

    @if ($facts !== [])
        <dl class="site-glance__facts">
            @foreach ($facts as $fact)
                <div class="site-glance__fact">
                    <dt>{{ $fact['label'] }}</dt>
                    <dd>{{ $fact['value'] }}</dd>
                </div>
            @endforeach
        </dl>
    @endif

    @if ($branches->isNotEmpty())
        <p class="site-glance__branches">
            <span>{{ __('Shipping from') }}</span>
            @foreach ($branches as $branch)
                <span class="site-chip">{{ $branch->name }}</span>
            @endforeach
        </p>
    @endif
</div>
