<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\ReportFavourite;
use App\Models\ReportRunsLog;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Report Centre index (UC-30).
 * Displays all accessible reports grouped by Sales, Inventory, Finance, Compliance, Audit, Staff.
 * Includes search, favourites toggling (star), recently run section, and audience tags.
 */
#[Layout('layouts.app')]
#[Title('Report Centre')]
class Index extends Component
{
    public string $search = '';

    public string $selectedCategory = 'all';

    public function mount(): void
    {
        $user = Auth::user();
        abort_if(! $user || $user->hasRole(Role::SystemAdmin) || ! $user->canAccessBusinessData(), 403);
    }

    public function toggleFavourite(string $reportKey): void
    {
        $userId = Auth::id();
        if (! $userId) {
            return;
        }

        $fav = ReportFavourite::where('user_id', $userId)->where('report_key', $reportKey)->first();
        if ($fav) {
            $fav->delete();
        } else {
            ReportFavourite::create([
                'user_id' => $userId,
                'report_key' => $reportKey,
            ]);
        }
    }

    public function render(ReportRegistry $registry): View
    {
        /** @var User $user */
        $user = Auth::user();
        $categories = $registry->byCategoryFor($user);

        // Favourites
        $favourites = ReportFavourite::where('user_id', $user->id)->pluck('report_key')->toArray();

        // Recently run (last 5 unique reports)
        $recentlyRunKeys = ReportRunsLog::where('user_id', $user->id)
            ->orderByDesc('run_at')
            ->pluck('report_key')
            ->unique()
            ->take(5)
            ->values()
            ->toArray();

        $recentReports = [];
        foreach ($recentlyRunKeys as $key) {
            $rep = $registry->get($key);
            if ($rep && $rep->canView($user)) {
                $recentReports[] = $rep;
            }
        }

        // Favourited report objects
        $favouriteReports = [];
        foreach ($favourites as $key) {
            $rep = $registry->get($key);
            if ($rep && $rep->canView($user)) {
                $favouriteReports[] = $rep;
            }
        }

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
            'favourites' => $favourites,
            'favouriteReports' => $favouriteReports,
            'recentReports' => $recentReports,
            'isStaff' => $user->hasRole(Role::PharmacyStaff) && ! $user->hasRole(Role::Owner) && ! $user->hasRole(Role::PharmacyManager),
        ]);
    }
}
