<div class="page-header d-flex flex-wrap align-items-center gap-3 mb-4">
    <div class="kpi-icon" style="width:52px;height:52px;border-radius:12px;background:var(--boels-orange);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.6rem;"><i class="bi bi-{{ $icoon }}"></i></div>
    <div class="flex-grow-1"><h1>{{ $titel }}</h1><p>{{ $tekst }}</p></div>
    <div class="small text-muted text-end">
        @if($materieel)Materieellijst: <strong>{{ number_format($materieelAantal, 0, ',', '.') }}</strong> machines<br>ingelezen {{ $materieel->created_at->format('d-m-Y H:i') }}@else<span class="text-danger">Nog geen materieellijst ingelezen</span>@endif
    </div>
</div>
