<!DOCTYPE html>
<html lang="nl"><head><meta charset="utf-8"><title>{{ $aanvraag->onderwerp }}</title></head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #333; margin: 0; padding: 0; background: #f5f5f5;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f5f5f5;padding:20px 0;"><tr><td align="center">
<table width="680" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:100%;">
    <tr><td style="background:#FF6600;color:#fff;padding:16px 24px;font-size:18px;font-weight:bold;">
        <span style="display:inline-block;background:#fff;color:#FF6600;border-radius:6px;padding:2px 9px;margin-right:10px;font-weight:800;">B</span>Boels Industrial — aanvraag materieel
    </td></tr>
    <tr><td style="padding:24px;">
        <div style="white-space:pre-wrap;line-height:1.5;">{{ $intro }}</div>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse;margin:18px 0;font-size:13px;">
            <thead><tr style="background:#f1f1f1;">
                <th align="left" style="border-bottom:2px solid #ddd;">Subgroep</th><th align="left" style="border-bottom:2px solid #ddd;">Omschrijving</th>
                <th align="left" style="border-bottom:2px solid #ddd;">Machinenr</th><th align="left" style="border-bottom:2px solid #ddd;">Merk / model</th>
                <th align="left" style="border-bottom:2px solid #ddd;">Status</th>
            </tr></thead>
            <tbody>
            @foreach($machines as $m)
            <tr><td style="border-bottom:1px solid #eee;">{{ $m['subgroep_nr'] }}</td><td style="border-bottom:1px solid #eee;">{{ $m['omschrijving'] }}</td>
                <td style="border-bottom:1px solid #eee;"><strong>{{ $m['uniek_nr'] }}</strong></td><td style="border-bottom:1px solid #eee;">{{ $m['merk_model'] }}</td>
                <td style="border-bottom:1px solid #eee;">{{ $m['status'] }}</td></tr>
            @endforeach
            </tbody>
        </table>
        <div style="white-space:pre-wrap;line-height:1.5;">{{ $afsluiting }}</div>
    </td></tr>
    <tr><td style="background:#f9f9f9;color:#888;font-size:11px;padding:12px 24px;">Verstuurd via de Boels Voorraad tool (voorraad.sorai.nl) · {{ $waarden['type'] }} {{ $waarden['referentie'] }} · aanvraag #{{ $aanvraag->id }}</td></tr>
</table>
</td></tr></table>
</body></html>
