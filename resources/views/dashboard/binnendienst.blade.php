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
            <li><strong>Fase 2:</strong> materieel-Excel, contract-Excel en project-Excel uploaden; per regel met status <em>Niet toegekend</em> zoeken in de materieellijst op subgroep (eerst eigen depot, daarna per depot, eerst Available, dan In Service, dan In Repair).</li>
            <li><strong>Fase 3:</strong> per depot met één klik een aanvraagmail versturen (template door de beheerder in te stellen).</li>
            <li><strong>Later:</strong> rekening houden met toekomstige reserveringen op het depot.</li>
            <li>Dynamische filters op contract, subgroep, machinenummer en depot.</li>
        </ul>
    </div>
</div>
@endsection
