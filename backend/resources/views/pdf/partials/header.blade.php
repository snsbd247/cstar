<table width="100%" style="border-bottom: 1.5pt solid #047857; padding-bottom: 4pt;">
    <tr>
        <td style="font-size: 14pt; font-weight: bold; color: #047857;">C-STAR</td>
        <td style="text-align: right; font-size: 11pt; font-weight: bold; color: #1e293b;">{{ $title }}</td>
    </tr>
    <tr>
        <td style="font-size: 7.5pt; color: #475569;">Center for Speech Therapy &amp; Autism Rehabilitation</td>
        <td style="text-align: right; font-size: 7.5pt; color: #475569;">{{ collect([$site['phone'] ?? '', $site['email'] ?? ''])->filter()->join(' · ') }}</td>
    </tr>
</table>
