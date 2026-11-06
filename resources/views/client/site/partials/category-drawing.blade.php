@php
    // One drawing per category, matched on a keyword so a renamed or added category degrades to the carton.
    $drawing = collect(['HYDRAULIC' => 'hydraulic', 'SUSPENSION' => 'suspension', 'ELECTRIC' => 'electric', 'BEARING' => 'bearing'])
        ->first(fn (string $file, string $word): bool => str_contains(strtoupper($name), $word), 'carton');
@endphp
@include('client.site.partials.drawings.'.$drawing, ['class' => $class])
