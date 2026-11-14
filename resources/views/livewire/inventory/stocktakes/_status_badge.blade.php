@php
    $colors = [
        'draft' => 'bg-slate-100 text-slate-700',
        'counting' => 'bg-blue-100 text-blue-800',
        'review' => 'bg-amber-100 text-amber-800',
        'approved' => 'bg-emerald-100 text-emerald-800',
        'rejected' => 'bg-rose-100 text-rose-800',
    ];
    $labels = [
        'draft' => __('stocktake.status_draft'),
        'counting' => __('stocktake.status_counting'),
        'review' => __('stocktake.status_review'),
        'approved' => __('stocktake.status_approved'),
        'rejected' => __('stocktake.status_rejected'),
    ];
@endphp
<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $colors[$status] ?? 'bg-slate-100 text-slate-700' }}">
    {{ $labels[$status] ?? $status }}
</span>

