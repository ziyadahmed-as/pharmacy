<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\ConfirmImport;
use App\Actions\Catalogue\PreviewImport;
use App\Models\ProductImport;
use App\Models\ProductImportRow;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class Import extends Component
{
    use WithFileUploads, WithPagination;

    public $file;

    #[Locked]
    public ?int $importId = null;

    #[Url]
    public string $filter = 'all'; // all, errors_only, valid_only

    public bool $isProcessing = false;

    public function mount(?int $importId = null): void
    {
        if ($importId) {
            $this->importId = $importId;
        }
    }

    public function uploadAndPreview(PreviewImport $action): void
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('catalogue.manage')) {
            abort(403);
        }

        $this->validate([
            'file' => ['required', 'file', 'max:20480', 'mimes:csv,txt,xlsx'],
        ]);

        $originalName = $this->file->getClientOriginalName();
        $storedPath = $this->file->store('imports', 'local');
        $fullPath = storage_path('app/'.$storedPath);

        try {
            $import = $action->execute($fullPath, $originalName, null, $user);
            $this->importId = $import->id;
            $this->reset('file');
            $this->resetPage();
        } catch (ValidationException $e) {
            $this->setErrorBag($e->validator->getMessageBag());
        }
    }

    public function confirm(ConfirmImport $action)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('catalogue.manage')) {
            abort(403);
        }

        $import = ProductImport::findOrFail($this->importId);
        $result = $action->execute($import, false, $user);

        session()->flash('message', __('catalogue.import_confirmed_success', [
            'saved' => $result['saved_count'],
            'rejected' => $result['rejected_count'],
        ]));

        return redirect()->route('catalogue.index');
    }

    public function cancel(ConfirmImport $action)
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('catalogue.manage')) {
            abort(403);
        }

        $import = ProductImport::findOrFail($this->importId);
        $action->execute($import, true, $user);

        session()->flash('message', __('catalogue.import_cancelled_success'));

        return redirect()->route('catalogue.index');
    }

    public function downloadErrorsCsv()
    {
        $import = ProductImport::findOrFail($this->importId);
        $errorRows = $import->rows()->where('status', 'error')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="import_errors_'.$import->id.'.csv"',
        ];

        $callback = function () use ($errorRows) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Row', 'Brand', 'Generic Name', 'Barcode', 'Errors']);

            foreach ($errorRows as $row) {
                $raw = $row->raw_data;
                $errs = implode('; ', $row->errors ?? []);
                fputcsv($handle, [
                    $row->row_number,
                    $raw['brand'] ?? '',
                    $raw['generic_name'] ?? '',
                    $raw['barcode'] ?? '',
                    $errs,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function render()
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('catalogue.manage')) {
            abort(403);
        }

        $import = $this->importId ? ProductImport::find($this->importId) : null;
        $rows = collect();

        if ($import) {
            $query = ProductImportRow::where('product_import_id', $import->id);
            if ($this->filter === 'errors_only') {
                $query->where('status', 'error');
            } elseif ($this->filter === 'valid_only') {
                $query->where('status', 'valid');
            }
            $rows = $query->orderBy('row_number')->paginate(25);
        }

        return view('livewire.catalogue.import', [
            'import' => $import,
            'rows' => $rows,
        ])->layout('layouts.app', ['title' => __('catalogue.import_title')]);
    }
}
