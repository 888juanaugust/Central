{{-- Previous or next, on the slide's left or right edge. --}}
<button type="button" class="site-promos__arrow site-promos__arrow--{{ $direction }}" {{ $direction === 'prev' ? 'data-prev' : 'data-next' }} aria-label="{{ $direction === 'prev' ? __('Previous slide') : __('Next slide') }}">
    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $direction === 'prev' ? 'M12.5 4 6.5 10l6 6' : 'm7.5 4 6 6-6 6' }}"/></svg>
</button>
