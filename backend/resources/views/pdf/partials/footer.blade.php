<table width="100%" style="border-top: 0.5pt solid #cbd5e1; font-size: 7.5pt; color: #64748b;">
    <tr>
        <td>{{ $site['footer_note'] }} {{ $site['address'] }}</td>
        <td style="text-align: right;">@if ($site['show_printed_date'])Printed {{ now()->format('d M Y') }} · @endif Page {PAGENO} of {nbpg}</td>
    </tr>
</table>
