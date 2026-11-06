{{-- A hydraulic cylinder in section. A generic drawing, not a catalogue item: no text, no dimensions. --}}
@php $hatch = 'hh-'.\Illuminate\Support\Str::slug($class); @endphp
<svg class="{{ $class }}" viewBox="0 0 360 220" fill="none" aria-hidden="true"><defs><pattern id="{{ $hatch }}" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0V6" stroke="currentColor" stroke-width=".9"/></pattern></defs>
<path d="M8 110H352" class="site-line--center"/>
<circle cx="34" cy="110" r="17"/><circle cx="34" cy="110" r="7"/><path d="M46 98 58 92M46 122l12 6"/>
<rect x="56" y="74" width="20" height="72"/>
<rect x="76" y="82" width="140" height="56"/>
<rect x="76" y="82" width="140" height="8" fill="url(#{{ $hatch }})"/><rect x="76" y="130" width="140" height="8" fill="url(#{{ $hatch }})"/>
<rect x="150" y="90" width="24" height="40" fill="url(#{{ $hatch }})"/><path d="M156 90v40M168 90v40"/>
<path d="M174 100H316M174 120H316"/>
<rect x="216" y="74" width="26" height="72"/><path d="M224 74v72M234 74v72"/>
<rect x="92" y="70" width="16" height="12"/><rect x="186" y="70" width="16" height="12"/>
<path d="M316 96h6l6-6M316 124h6l6 6"/><circle cx="334" cy="110" r="15"/><circle cx="334" cy="110" r="6"/>
</svg>
