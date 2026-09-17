@extends('layouts.app')
@section('titel', 'Manager')
@section('inhoud')
<div class="page-header d-flex align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-bar-chart-line"></i></div>
    <div><h1>Manager</h1><p>Heeft elk depot voldoende voorraad en hoeveel staat er op service of reparatie</p></div>
</div>
@include('dashboard._depots')
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Wat komt er op dit dashboard</div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>Fase 4:</strong> per depot: minimale voorraad gehaald ja/nee, aantal machines in Service en In Repair, tekorten per subgroep.</li>
            <li>Vergelijking per area en over alle depots, met filters op depot, area en subgroep.</li>
        </ul>
    </div>
</div>
@endsection
