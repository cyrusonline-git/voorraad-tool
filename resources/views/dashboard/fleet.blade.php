@extends('layouts.app')
@section('titel', 'Fleet')
@section('inhoud')
<div class="page-header d-flex align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-truck"></i></div>
    <div><h1>Fleet</h1><p>Totaaloverzicht: welk materieel staat waar, met welke status</p></div>
</div>
@include('dashboard._depots')
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Wat komt er op dit dashboard</div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>Fase 2:</strong> de volledige materieellijst (laatste upload) doorzoekbaar op machinenummer, subgroep, depot, area en status.</li>
            <li><strong>Fase 4:</strong> dezelfde voorraad- en service-overzichten als de manager, over alle depots.</li>
        </ul>
    </div>
</div>
@endsection
