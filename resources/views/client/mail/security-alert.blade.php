{{ __('The security watch of :company found this at :time:', ['company' => $company, 'time' => now()->format('d/m/Y H:i')]) }}

@foreach ($findings as $finding)
- {{ strtoupper($finding->severity->label()) }}: {{ $finding->line() }}
@endforeach

{{ __('The Activity Log has every row behind these lines; the Operations screen shows the last day. Runbook: docs/BLUE-TEAM.md.') }}
{{ __('Each finding is reported once per six hours for the same account, address or document.') }}
