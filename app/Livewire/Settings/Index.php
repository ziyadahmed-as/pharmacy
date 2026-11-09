<?php

namespace App\Livewire\Settings;

use App\Actions\Settings\RequestSettingChange;
use App\Models\ApprovalRequest;
use App\Services\SettingsRepository;
use App\Support\SettingsCatalogue;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Settings by group. SA requests changes; PM and Owner see them read-only and approve (UC-38). */
class Index extends Component
{
    use WithFileUploads;

    #[Url]
    public string $group = 'profile';

    #[Locked]
    public ?string $editKey = null;

    public string $value = '';

    public string $reason = '';

    public $logo = null;

    public ?string $historyKey = null;

    public function mount(): void
    {
        Gate::authorize('settings.view');
        if (! in_array($this->group, SettingsCatalogue::groups(), true)) {
            $this->group = 'profile';
        }
    }

    public function edit(string $key, SettingsRepository $settings): void
    {
        Gate::authorize('settings.edit');
        abort_unless(SettingsCatalogue::find($key), 404);
        $this->resetErrorBag();
        $this->editKey = $key;
        $current = $settings->get($key);
        $this->value = is_array($current) ? implode(', ', $current) : (is_bool($current) ? ($current ? '1' : '0') : (string) ($current ?? ''));
        $this->reason = '';
        $this->logo = null;
    }

    public function cancel(): void
    {
        $this->reset(['editKey', 'value', 'reason', 'logo']);
    }

    public function showHistory(?string $key): void
    {
        $this->historyKey = $key;
    }

    public function request(RequestSettingChange $action): void
    {
        Gate::authorize('settings.edit');
        $key = (string) $this->editKey;
        $raw = $this->value;

        try {
            if ($key === 'profile.logo') {
                $this->validate(['logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024']]);
                $raw = $this->logo->store('settings', 'local');
            }
            $action->execute(Auth::user(), $key, $raw, $this->reason ?: null);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $f => $m) {
                $this->addError($f === 'value' || $f === 'key' ? 'value' : $f, $m[0]);
            }

            return;
        }

        $this->cancel();
        session()->flash('status', __('settings.request_sent'));
    }

    public function render(SettingsRepository $settings)
    {
        Gate::authorize('settings.view');

        $pending = ApprovalRequest::pending()->where('type', 'settings.update')->get()->keyBy(fn ($r) => $r->payload['key'] ?? '');

        $rows = [];
        foreach (SettingsCatalogue::all() as $key => $def) {
            if ($def['group'] !== $this->group) {
                continue;
            }
            $rows[$key] = $def + ['current' => $settings->get($key), 'notSet' => $settings->isNotSet($key), 'pending' => $pending->get($key)];
        }

        $history = $this->historyKey
            ? DB::table('setting_histories')->where('key', $this->historyKey)->orderByDesc('id')->limit(20)->get()
            : collect();

        return view('livewire.settings.index', [
            'groups' => SettingsCatalogue::groups(),
            'rows' => $rows,
            'canEdit' => Auth::user()->hasPermission('settings.edit'),
            'history' => $history,
        ])->layout('layouts.app', ['title' => __('nav.settings')]);
    }
}
