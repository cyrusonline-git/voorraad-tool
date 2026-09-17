@extends('layouts.app')
@section('titel', 'Mailtemplates')
@section('inhoud')
<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="flex-grow-1"><h1><i class="bi bi-envelope me-2 text-boels"></i>Mailtemplates &amp; testmail</h1><p>Teksten van de aanvraagmail aan depots. Mailer: <code>{{ $mailer }}</code>, afzender: <code>{{ $van }}</code> (gedeeld met Boels CORE).</p></div>
    <form method="post" action="{{ route('admin.mail.test') }}">@csrf<button class="btn btn-outline-boels"><i class="bi bi-send me-1"></i>Testmail naar mijzelf</button></form>
    <form method="post" action="{{ route('admin.mail.herstel') }}" onsubmit="return confirm('Standaardteksten terugzetten?')">@csrf<button class="btn btn-outline-secondary"><i class="bi bi-arrow-counterclockwise me-1"></i>Standaard herstellen</button></form>
</div>
<form method="post" action="{{ route('admin.mail.opslaan') }}">
@csrf
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card"><div class="card-header">Teksten</div><div class="card-body">
            <div class="mb-3"><label class="form-label fw-semibold">Onderwerp</label><input type="text" name="mail_onderwerp" class="form-control" value="{{ $waarden['mail_onderwerp'] }}"></div>
            <div class="mb-3"><label class="form-label fw-semibold">Inleiding (boven de machinelijst)</label><textarea name="mail_intro" rows="7" class="form-control">{{ $waarden['mail_intro'] }}</textarea></div>
            <div class="mb-3"><label class="form-label fw-semibold">Afsluiting (onder de machinelijst)</label><textarea name="mail_afsluiting" rows="7" class="form-control">{{ $waarden['mail_afsluiting'] }}</textarea></div>
            <button class="btn btn-boels"><i class="bi bi-save me-1"></i>Opslaan</button>
        </div></div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-3"><div class="card-header">Placeholders</div><div class="card-body small">
            <table class="table table-sm mb-0">@foreach($placeholders as $p => $uitleg)<tr><td><code>{{ $p }}</code></td><td>{{ $uitleg }}</td></tr>@endforeach</table>
        </div></div>
        <div class="card"><div class="card-header">Voorbeeld (met fictieve gegevens)</div><div class="card-body small">
            <div class="fw-semibold mb-2">{{ $preview['mail_onderwerp'] }}</div>
            <div style="white-space:pre-wrap">{{ $preview['mail_intro'] }}</div>
            <div class="text-muted my-2 fst-italic">[tabel met de aangevinkte machines]</div>
            <div style="white-space:pre-wrap">{{ $preview['mail_afsluiting'] }}</div>
        </div></div>
    </div>
</div>
</form>
@endsection
