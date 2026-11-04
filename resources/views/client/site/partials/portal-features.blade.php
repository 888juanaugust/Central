@php
    // What a buyer gets after sign-in, in the order the portal puts it (CLAUDE.md, Priorities). Heroicons outlines, inlined.
    $features = [
        ['M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99',
            __('Repeat your last order'), __('It opens with the same parts and quantities. Change what you need, and send.')],
        ['M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 0 0 2.25-2.25V6.75A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25v10.5A2.25 2.25 0 0 0 4.5 19.5Z',
            __('Your free credit, always in view'), __('What you can still order on credit stands at the top of every page, with a word before an invoice grows too old.')],
        ['m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
            __('The catalogue at your own prices'), __('Search by brand, category, vehicle or part number. Every price is the one agreed with you.')],
        ['M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            __('Order history with its documents'), __('Every order with where it stands, and its invoice and surat jalan as PDFs.')],
        ['M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5',
            __('Open invoices with their due dates'), __('What is still open, when it falls due, and how old it is.')],
    ];
    // The picture of the reorder card: parts and quantities only, never an amount.
    $orderNumber = 'SO-JKT-2610-0142';
    $lines = [
        ['ASTRO', __('Brake master cylinder'), '12', 'PCS'],
        ['OSBORN', __('Front shock absorber'), '2', 'CTN'],
        ['SERVO', __('Front wheel bearing'), '24', 'PCS'],
        ['STAVO', __('Ignition coil'), '6', 'PCS'],
    ];
@endphp
<section class="site-section" data-reveal>
    <div class="site-portal">
        <div class="site-portal__copy">
            <h2 class="site-h2">{{ __('What you get after sign-in') }}</h2>
            <p class="site-lede">{{ __('The customer portal is built for restocking: the same parts, again and again, without a phone call.') }}</p>
            <ul class="site-portal__list">
                @foreach ($features as [$path, $title, $text])
                    <li class="site-portal__item">
                        <span class="site-portal__icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}"/></svg></span>
                        <div>
                            <h3 class="site-portal__title">{{ $title }}</h3>
                            <p class="site-portal__text">{{ $text }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('site.sign_in') }}" class="site-link">{{ __('Sign in to the portal') }} @include('client.site.partials.arrow')</a>
        </div>

        <figure class="site-portal-mock" role="img" aria-label="{{ __('A picture of the portal: the last order, ready to repeat') }}">
            <div class="site-portal-mock__window" aria-hidden="true">
                <div class="site-portal-mock__bar"><span></span><span></span><span></span></div>
                <div class="site-portal-mock__body">
                    <div class="site-portal-mock__credit">
                        <div class="site-portal-mock__credit-row">
                            <span>{{ __('Free credit') }}</span>
                            <span class="site-portal-mock__percent">64%</span>
                        </div>
                        <div class="site-portal-mock__meter"><span></span></div>
                    </div>
                    <div class="site-portal-mock__card">
                        <div class="site-portal-mock__head">
                            <p class="site-portal-mock__title">{{ __('Repeat your last order') }}</p>
                            <p class="site-portal-mock__meta">{{ $orderNumber }}</p>
                        </div>
                        <ul class="site-portal-mock__lines">
                            @foreach ($lines as [$brand, $name, $quantity, $unit])
                                <li class="site-portal-mock__line">
                                    <div>
                                        <p class="site-portal-mock__name">{{ $name }}</p>
                                        <p class="site-portal-mock__brand">{{ $brand }}</p>
                                    </div>
                                    <span class="site-portal-mock__qty"><span class="site-portal-mock__step">&minus;</span><span class="site-portal-mock__num">{{ $quantity }}</span><span class="site-portal-mock__step">+</span></span>
                                    <span class="site-portal-mock__unit">{{ $unit }}</span>
                                </li>
                            @endforeach
                        </ul>
                        <span class="site-portal-mock__send">{{ __('Order again') }}</span>
                    </div>
                </div>
            </div>
        </figure>
    </div>
</section>
