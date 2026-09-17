@extends('layouts.app')
@section('titel', 'Binnendienst')
@section('inhoud')
<div class="page-header d-flex align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-headset"></i></div>
    <div><h1>Binnendienst</h1><p>Contract of project uploaden en zien op welk depot het materieel beschikbaar is</p></div>
</div>
@include('dashboard._depots')
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Wat komt er op dit dashboard</div>
    <div class="card-body">
        <ul class="mb-0">
            <li><a href="{{ route('uploads.index') }}">Uploads</a>: materieellijst, contract- en project-Excel inlezen.</li>
            <li>Bij een contract/project: <strong>Beschikbaarheid zoeken</strong> — regels "Niet toegekend" worden gezocht in de materieellijst, eerst op het eigen depot, daarna per depot (Available → In Service → In Repair).</li>
            <li>Per depot met één klik een <strong>aanvraagmail</strong>; alle verstuurde aanvragen staan onder <a href="{{ route('aanvragen.index') }}">Aanvragen</a>.</li>
            <li><a href="{{ route('voorraad.depots') }}">Voorraad</a>: minimale voorraad en service/reparatie per depot.</li>
            <li><strong>Later:</strong> rekening houden met toekomstige reserveringen op het depot.</li>
        </ul>
    </div>
</div>
@endsection
