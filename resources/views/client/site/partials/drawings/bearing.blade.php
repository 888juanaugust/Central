{{-- A ball bearing, front view and section, projected. A generic drawing, not a catalogue item: no text, no dimensions. --}}
@php $hatch = 'hb-'.\Illuminate\Support\Str::slug($class); @endphp
<svg class="{{ $class }}" viewBox="0 0 360 220" fill="none" aria-hidden="true"><defs><pattern id="{{ $hatch }}" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><path d="M0 0V6" stroke="currentColor" stroke-width=".9"/></pattern></defs>
<path d="M6 110H352M100 14V206" class="site-line--center"/>
<path d="M100 26H226M100 194H226M100 40H226M100 180H226" class="site-line--faint"/>
<circle cx="100" cy="110" r="84"/><circle cx="100" cy="110" r="70"/><circle cx="100" cy="110" r="44"/><circle cx="100" cy="110" r="30"/>
<circle cx="100" cy="110" r="57" class="site-line--center"/><circle cx="157" cy="110" r="13.5"/><circle cx="143.66" cy="146.64" r="13.5"/><circle cx="109.9" cy="166.13" r="13.5"/><circle cx="71.5" cy="159.36" r="13.5"/><circle cx="46.44" cy="129.5" r="13.5"/><circle cx="46.44" cy="90.5" r="13.5"/><circle cx="71.5" cy="60.64" r="13.5"/><circle cx="109.9" cy="53.87" r="13.5"/><circle cx="143.66" cy="73.36" r="13.5"/>
<rect x="232" y="26" width="70" height="14" fill="url(#{{ $hatch }})"/><rect x="232" y="66" width="70" height="14" fill="url(#{{ $hatch }})"/><circle cx="267" cy="53" r="13.5"/><rect x="232" y="180" width="70" height="14" fill="url(#{{ $hatch }})"/><rect x="232" y="140" width="70" height="14" fill="url(#{{ $hatch }})"/><circle cx="267" cy="167" r="13.5"/>
<path d="M232 26V194M302 26V194" class="site-line--faint"/>
</svg>
