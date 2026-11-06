{{--
    The cookie notice, as the previous site had it: honest about what it is. The site sets
    technical cookies only, so there is nothing to refuse and the one control is
    "Understood". Hidden until site.js has checked the browser's local storage (never a
    cookie, which would itself need naming); it never flashes for somebody who has seen it.
--}}
<div class="site-notice" data-site-notice hidden role="region" aria-label="{{ __('Cookie notice') }}">
    <div class="site-notice__text">
        <p class="site-notice__title">{{ __('Only technical cookies') }}</p>
        <p class="site-notice__body">{{ __('This site sets only what it needs to work: your session, protection against forged forms, and the language you choose. No tracking and no advertising.') }}</p>
    </div>
    <div class="site-notice__actions">
        <a href="{{ route('site.privacy') }}" class="site-link site-link--sm">{{ __('Privacy policy') }}</a>
        <button type="button" class="site-btn site-btn--primary site-btn--sm" data-site-notice-close>{{ __('Understood') }}</button>
    </div>
</div>
