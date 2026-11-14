<?php

namespace App\Livewire\Reports;

use App\Enums\Role;
use App\Models\Category;
use App\Models\User;
use App\Reports\ReportRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Run Report')]
class Run extends Component
{
    public string $type = '';

    #[Url]
    public string $mode = 'month'; // 'day' | 'week' | 'month' | 'year' | 'custom'

    #[Url]
    public string $startDate = '';

    #[Url]
    public string $endDate = '';

    #[Url]
    public string $groupBy = 'day';

    #[Url]
    public string $search = '';

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $userId = '';

    #[Url]
    public string $status = '';

    public float $renderDurationMs = 0.0;

    public function mount(string $type, ReportRegistry $registry): void
    {
        $this->type = $type;
        /** @var User|null $user */
        $user = Auth::user();

        abort_if(! $user || $user->hasRole(Role::SystemAdmin) || ! $user->canAccessBusinessData(), 403);

        $definition = $registry->get($type);
        abort_unless($definition && $definition->canView($user), 403);

        if ($this->startDate === '') {
            $this->startDate = now()->startOfMonth()->toDateString();
        }

        if ($this->endDate === '') {
            $this->endDate = now()->toDateString();
        }
    }

    public function setToday(): void
    {
        $this->mode = 'day';
        $this->startDate = now()->toDateString();
        $this->endDate = now()->toDateString();
        $this->groupBy = 'day';
    }

    public function setYesterday(): void
    {
        $this->mode = 'day';
        $this->startDate = now()->subDay()->toDateString();
        $this->endDate = now()->subDay()->toDateString();
        $this->groupBy = 'day';
    }

    public function setThisWeek(): void
    {
        $this->mode = 'week';
        $this->startDate = now()->startOfWeek()->toDateString();
        $this->endDate = now()->toDateString();
        $this->groupBy = 'day';
    }

    public function setThisMonth(): void
    {
        $this->mode = 'month';
        $this->startDate = now()->startOfMonth()->toDateString();
        $this->endDate = now()->toDateString();
        $this->groupBy = 'day';
    }

    public function setThisYear(): void
    {
        $this->mode = 'year';
        $this->startDate = now()->startOfYear()->toDateString();
        $this->endDate = now()->toDateString();
        $this->groupBy = 'month';
    }

    public function render(ReportRegistry $registry): View
    {
        /** @var User $user */
        $user = Auth::user();

        $definition = $registry->get($this->type);
        abort_unless($definition && $definition->canView($user), 403);

        $filters = [
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'group_by' => $this->groupBy,
            'search' => $this->search,
            'category_id' => $this->categoryId,
            'user_id' => $this->userId,
            'status' => $this->status,
        ];

        $startTime = microtime(true);
        $result = $definition->query($filters, $user);
        $this->renderDurationMs = round((microtime(true) - $startTime) * 1000, 2);

        $categories = Category::orderBy('name')->get();
        $staffUsers = User::whereHas('roles', fn ($q) => $q->where('slug', Role::PharmacyStaff->value))->get();

        return view('livewire.reports.run', [
            'definition' => $definition,
            'result' => $result,
            'categories' => $categories,
            'staffUsers' => $staffUsers,
            'canViewCost' => $definition->canViewCost($user),
            'currency' => setting('profile.currency', 'ETB'),
        ]);
    }
}
