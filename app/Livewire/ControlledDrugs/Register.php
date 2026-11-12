<?php

namespace App\Livewire\ControlledDrugs;

use App\Models\ControlledDrugRegister;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Controlled drug register viewer (read-only, exportable by PM and Owner).
 *
 * @uc UC-22
 *
 * @fr FR-4.4, FR-4.3
 *
 * @ac AC-34
 */
class Register extends Component
{
    use WithPagination;

    public string $search = '';

    public string $type = '';

    public string $fromDate = '';

    public string $toDate = '';

    protected $queryString = [
        'search' => ['except' => ''],
        'type' => ['except' => ''],
        'fromDate' => ['except' => ''],
        'toDate' => ['except' => ''],
    ];

    public function mount(): void
    {
        abort_unless((bool) setting('pharmacy.handles_controlled_drugs', true), 404);
        abort_unless(auth()->user()?->hasPermission('controlled_drugs.view'), 403);
        abort_if(auth()->user()->isSystemAdmin(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingType(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $query = ControlledDrugRegister::with(['product', 'batch', 'user', 'secondPerson', 'sale'])
            ->orderBy('id', 'desc');

        if ($this->search !== '') {
            $s = '%'.$this->search.'%';
            $query->where(function ($q) use ($s) {
                $q->whereHas('product', fn ($pq) => $pq->where('brand', 'like', $s)->orWhere('generic_name', 'like', $s))
                    ->orWhereHas('batch', fn ($bq) => $bq->where('batch_no', 'like', $s))
                    ->orWhere('patient_name', 'like', $s)
                    ->orWhere('prescriber_name', 'like', $s);
            });
        }

        if ($this->type !== '') {
            $query->where('entry_type', $this->type);
        }

        if ($this->fromDate !== '') {
            $query->whereDate('created_at', '>=', $this->fromDate);
        }

        if ($this->toDate !== '') {
            $query->whereDate('created_at', '<=', $this->toDate);
        }

        $entries = $query->paginate(20);

        return view('livewire.controlled-drugs.register', [
            'entries' => $entries,
        ])->title(__('controlled_drugs.register_title'));
    }
}
