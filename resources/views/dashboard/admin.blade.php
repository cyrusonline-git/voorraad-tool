@extends('layouts.app')
@section('titel', 'Beheer')
@section('inhoud')
<div class="page-header d-flex align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-gear"></i></div>
    <div><h1>Beheer</h1><p>Instellingen, depotkoppeling en mailtemplates — toegang wordt in Boels CORE beheerd</p></div>
</div>
@include('dashboard._depots')
<div class="card">
    <div class="card-header"><i class="bi bi-list-check me-2 text-boels"></i>Wat komt er op dit dashboard</div>
    <div class="card-body">
        <ul class="mb-0">
            <li>Nu al: <a href="{{ route('admin.depots') }}">Depots &amp; areas</a> uit CORE ophalen en per depot het depotnummer (materieel-Excel) en aanvraag-mailadres vastleggen; <a href="{{ route('admin.instellingen') }}">Instellingen</a>.</li>
            <li><strong>Fase 2:</strong> kolomindeling van de drie Excel-uploads instelbaar; statusvertalingen (NL/EN).</li>
            <li><strong>Fase 3:</strong> mailtemplates voor de aanvraagmail naar depots; SMTP-instellingen.</li>
            <li>Toegang en rollen: uitsluitend in Boels CORE (Beheer → Gebruikers → Voorraad tool).</li>
        </ul>
    </div>
</div>
@endsection
