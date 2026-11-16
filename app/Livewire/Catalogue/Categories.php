<?php

namespace App\Livewire\Catalogue;

use App\Actions\Catalogue\DeleteCategory;
use App\Actions\Catalogue\SaveCategory;
use App\Actions\Catalogue\ToggleCategoryStatus;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Categories')]
class Categories extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = 'all'; // all, active, inactive

    public bool $showModal = false;

    public bool $showDeleteModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public ?int $parent_category_id = null;

    public string $description = '';

    public bool $is_active = true;

    public ?int $deletingId = null;

    public string $deletingName = '';

    public int $deletingProductCount = 0;

    public int $deletingChildCount = 0;

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    protected function authorizeManage(): void
    {
        $user = Auth::user();
        abort_unless($user && $user->canAccessBusinessData() && $user->hasPermission('catalogue.manage'), 403);
    }

    public function openCreateModal(): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'name', 'parent_category_id', 'description']);
        $this->is_active = true;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $category = Category::findOrFail($id);
        $this->editingId = $category->id;
        $this->name = $category->name;
        $this->parent_category_id = $category->parent_category_id;
        $this->description = $category->description ?? '';
        $this->is_active = (bool) $category->is_active;
        $this->resetErrorBag();
        $this->showModal = true;
    }

    public function save(SaveCategory $action): void
    {
        $this->authorizeManage();

        $category = $this->editingId ? Category::findOrFail($this->editingId) : null;

        try {
            $action->execute([
                'name' => $this->name,
                'parent_category_id' => $this->parent_category_id,
                'description' => $this->description,
                'is_active' => $this->is_active,
            ], $category, Auth::user());

            $this->showModal = false;
            $this->reset(['editingId', 'name', 'parent_category_id', 'description']);
            session()->flash('message', $category ? __('catalogue.category_updated_success') : __('catalogue.category_created_success'));
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }
        }
    }

    public function toggleStatus(int $id, ToggleCategoryStatus $action): void
    {
        $this->authorizeManage();
        $category = Category::findOrFail($id);

        try {
            $action->execute($category, Auth::user());
            session()->flash('message', __('catalogue.category_status_updated'));
        } catch (ValidationException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function confirmDelete(int $id): void
    {
        $this->authorizeManage();
        $category = Category::withCount(['products', 'children'])->findOrFail($id);
        $this->deletingId = $category->id;
        $this->deletingName = $category->name;
        $this->deletingProductCount = $category->products_count;
        $this->deletingChildCount = $category->children_count;
        $this->resetErrorBag();
        $this->showDeleteModal = true;
    }

    public function delete(DeleteCategory $action): void
    {
        $this->authorizeManage();
        if (! $this->deletingId) {
            return;
        }

        $category = Category::findOrFail($this->deletingId);

        try {
            $action->execute($category, Auth::user());
            $this->showDeleteModal = false;
            $this->reset(['deletingId', 'deletingName', 'deletingProductCount', 'deletingChildCount']);
            session()->flash('message', __('catalogue.category_deleted_success'));
        } catch (ValidationException $e) {
            $this->addError('delete', $e->validator->errors()->first('delete') ?: $e->getMessage());
        }
    }

    public function render()
    {
        $this->authorizeManage();

        $query = Category::query()
            ->with('parent')
            ->withCount(['products', 'children']);

        if (trim($this->search) !== '') {
            $term = '%'.trim($this->search).'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('description', 'like', $term);
            });
        }

        if ($this->statusFilter === 'active') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'inactive') {
            $query->where('is_active', false);
        }

        $categories = $query->orderBy('name')->paginate(15);

        $eligibleParents = Category::query()
            ->when($this->editingId, fn ($q) => $q->where('id', '!=', $this->editingId))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('livewire.catalogue.categories', [
            'categories' => $categories,
            'eligibleParents' => $eligibleParents,
        ]);
    }
}
