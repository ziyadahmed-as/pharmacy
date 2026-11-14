<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Reports & Analytics')]
class Index extends Component
{
    public string $search = '';

    public string $selectedCategory = 'all';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin) || ! $user->canAccessBusinessData(), 403);
    }

    public function render(ReportRegistry $registry): View
    {
        /** @var User $user */
        $user = Auth::user();
        $categories = $registry->byCategoryFor($user);

        if ($this->selectedCategory !== 'all' && isset($categories[$this->selectedCategory])) {
            $categories = [$this->selectedCategory => $categories[$this->selectedCategory]];
        }

        if ($this->search !== '') {
            $term = strtolower($this->search);
            foreach ($categories as $catKey => &$catData) {
                $catData['reports'] = array_filter(
                    $catData['reports'],
                    fn ($rep) => str_contains(strtolower($rep->name()), $term) || str_contains(strtolower($rep->description()), $term)
                );
            }
            $categories = array_filter($categories, fn ($g) => ! empty($g['reports']));
        }

        return view('livewire.reports.index', [
            'categories' => $categories,
            'isStaff' => $user->hasRole(Role::PharmacyStaff) && ! $user->hasRole(Role::Owner) && ! $user->hasRole(Role::PharmacyManager),
        ]);
    }
}
