@extends('layouts.app')
@section('titel', 'Werkplaats')
@section('inhoud')
<div class="page-header d-flex align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-wrench-adjustable"></i></div>
    <div><h1>Werkplaats</h1><p>Wat moet de werkplaats nakijken om de minimale voorraad op het depot te halen</p></div>
</div>
@include('dashboard._depots')
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Wat komt er op dit dashboard</div>
    <div class="card-body">
        <ul class="mb-0">
            <li><strong>Fase 4:</strong> minimale voorraad per subgroep voor jouw depot; tekorten met de machines in <em>In Service</em> / <em>In Repair</em> die je kunt nakijken om weer op voorraad te komen.</li>
            <li><strong>Fase 4:</strong> aankomende orders voor jouw depot (ingangsdatum binnen 2 weken, instelbaar) waar geen beschikbare voorraad voor is.</li>
            <li>Filters op subgroep, machinenummer en status.</li>
        </ul>
    </div>
</div>
@endsection
