@include('pdf.partials.styles')
@php
    $fmt = function ($value, string $type) {
        if ($value === null || $value === '') return '—';
        return match ($type) {
            'money' => number_format((float) $value, 2),
            'percent' => $value.'%',
            'decimal' => number_format((float) $value, 1),
            'date' => \Illuminate\Support\Carbon::parse($value)->format('d M Y'),
            default => $value,
        };
    };
    $right = fn (string $type) => in_array($type, ['money', 'number', 'percent', 'decimal'], true) ? 'text-align:right' : '';
@endphp
<p class="muted">{{ \Illuminate\Support\Carbon::parse($report['from'])->format('d M Y') }} – {{ \Illuminate\Support\Carbon::parse($report['to'])->format('d M Y') }}@if(! empty($report['note'])) · {{ $report['note'] }}@endif</p>
<table class="grid" style="font-size:8.5pt;">
    <tr>
        @foreach($report['columns'] as $c)
            <th style="{{ $right($c['type']) }}">{{ $c['label'] }}{{ $c['type'] === 'money' ? ' (Tk)' : '' }}</th>
        @endforeach
    </tr>
    @forelse($report['rows'] as $row)
        <tr>
            @foreach($report['columns'] as $c)
                <td style="{{ $right($c['type']) }}">{{ $fmt($row[$c['key']] ?? null, $c['type']) }}</td>
            @endforeach
        </tr>
    @empty
        <tr><td colspan="{{ count($report['columns']) }}" class="muted">No data for this period.</td></tr>
    @endforelse
    @if($report['totals'])
        <tr>
            @foreach($report['columns'] as $c)
                <td style="{{ $right($c['type']) }}"><b>{{ isset($report['totals'][$c['key']]) ? $fmt($report['totals'][$c['key']], $c['type'] === 'date' ? 'text' : $c['type']) : '' }}</b></td>
            @endforeach
        </tr>
    @endif
</table>
