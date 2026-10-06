<table width="100%" style="border-bottom: 1.5pt solid {{ $site['color'] }}; padding-bottom: 4pt;">
    <tr>
        <td style="font-size: 14pt; font-weight: bold; color: {{ $site['color'] }};">{{ $site['short_name'] }}</td>
        <td style="text-align: right; font-size: 11pt; font-weight: bold; color: #1e293b;">{{ $title }}</td>
    </tr>
    <tr>
        <td style="font-size: 7.5pt; color: #475569;">{{ $site['full_name'] }}</td>
        <td style="text-align: right; font-size: 7.5pt; color: #475569;">{{ collect([$site['phone'], $site['email'], $site['website']])->filter()->join(' · ') }}</td>
    </tr>
    @if ($site['registration_no'] || $site['tin'])
        <tr>
            <td colspan="2" style="font-size: 7pt; color: #64748b;">{{ collect([$site['registration_no'] ? 'Reg. No. '.$site['registration_no'] : null, $site['tin'] ? 'TIN '.$site['tin'] : null])->filter()->join(' · ') }}</td>
        </tr>
    @endif
</table>
