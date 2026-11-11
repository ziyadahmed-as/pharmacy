<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ $customer->name }}</h1>
                @if($customer->consent_given)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                        ✓ {{ __('customers.consent_given') }}
                    </span>
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                        {{ __('customers.no_consent') }}
                    </span>
                @endif
            </div>
            <p class="text-sm font-mono text-slate-500 dark:text-slate-400 mt-1">{{ $customer->phone }}</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('customers.index') }}" class="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                {{ __('customers.back_to_list') }}
            </a>
            @if($canManage)
                <a href="{{ route('customers.edit', $customer) }}" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded-lg text-sm font-medium transition shadow-sm">
                    {{ __('customers.edit_profile') }}
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 text-sm text-emerald-800 dark:text-emerald-200">
            {{ session('success') }}
        </div>
    @endif

    <!-- Tabs Navigation -->
    <div class="border-b border-slate-200 dark:border-slate-700 flex space-x-6">
        <button
            type="button"
            wire:click="setTab('profile')"
            class="pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'profile' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}"
        >
            {{ __('customers.profile_tab') }}
        </button>

        @if($canViewHealth)
            <button
                type="button"
                wire:click="setTab('health')"
                class="pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'health' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}"
            >
                {{ __('customers.health_tab') }}
            </button>
        @endif

        <button
            type="button"
            wire:click="setTab('history')"
            class="pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'history' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}"
        >
            {{ __('customers.history_tab') }} ({{ $sales->count() }})
        </button>

        @if($canManage)
            <button
                type="button"
                wire:click="setTab('audit')"
                class="pb-3 text-sm font-medium border-b-2 transition {{ $activeTab === 'audit' ? 'border-teal-500 text-teal-600 dark:text-teal-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400' }}"
            >
                {{ __('customers.audit_tab') }} ({{ $healthViews->count() }})
            </button>
        @endif
    </div>

    <!-- Tab 1: Profile -->
    @if($activeTab === 'profile')
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('customers.name') }}</dt>
                    <dd class="mt-1 text-sm font-medium text-slate-900 dark:text-white">{{ $customer->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('customers.phone') }}</dt>
                    <dd class="mt-1 text-sm font-mono text-slate-900 dark:text-white">{{ $customer->phone }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('customers.address') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $customer->address ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('customers.birth_date') }}</dt>
                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                        {{ $customer->birth_date ? $customer->birth_date->format('Y-m-d') : '—' }}
                    </dd>
                </div>
                <div class="sm:col-span-2 pt-4 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                    <div>
                        <dt class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('customers.consent_status') }}</dt>
                        <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                            @if($customer->consent_given)
                                <span class="text-emerald-600 dark:text-emerald-400 font-medium">
                                    {{ __('customers.consent_given') }} ({{ $customer->consent_at ? $customer->consent_at->format('Y-m-d H:i') : '' }})
                                </span>
                            @else
                                <span class="text-slate-500 dark:text-slate-400">
                                    {{ __('customers.no_consent') }}
                                </span>
                            @endif
                        </dd>
                    </div>

                    @if($canManage)
                        <button
                            type="button"
                            wire:click="toggleConsent"
                            wire:confirm="{{ $customer->consent_given ? 'Revoking consent will remove saved allergy and chronic condition records. Proceed?' : 'Confirm that informed consent has been obtained from the customer?' }}"
                            class="px-3 py-1.5 border border-slate-300 dark:border-slate-600 rounded-lg text-xs font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition"
                        >
                            {{ $customer->consent_given ? 'Revoke Consent' : 'Record Consent' }}
                        </button>
                    @endif
                </div>
            </dl>
        </div>
    @endif

    <!-- Tab 2: Health & Allergies (A1, E2, AC-26) -->
    @if($activeTab === 'health' && $canViewHealth)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('customers.health_section_title') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('customers.health_fields_restricted') }}</p>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                    🔒 Access Audited
                </span>
            </div>

            @if(!$customer->consent_given)
                <div class="p-4 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800 text-sm text-amber-800 dark:text-amber-300">
                    {{ __('customers.consent_required_notice') }}
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                            {{ __('customers.allergies_label') }}
                        </h3>
                        <p class="text-sm text-slate-900 dark:text-white whitespace-pre-line font-medium">
                            {{ $healthData['allergies'] ?? __('customers.no_health_data_recorded') }}
                        </p>
                    </div>

                    <div class="p-4 rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">
                            {{ __('customers.chronic_conditions_label') }}
                        </h3>
                        <p class="text-sm text-slate-900 dark:text-white whitespace-pre-line font-medium">
                            {{ $healthData['chronic_conditions'] ?? __('customers.no_health_data_recorded') }}
                        </p>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Tab 3: Purchase History (FR-5.2) -->
    @if($activeTab === 'history')
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('customers.purchase_history_title') }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('customers.receipt_no') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.date') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.items') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.total') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($sales as $sale)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 font-mono font-medium text-slate-900 dark:text-white">
                                    <a href="{{ route('sales.show', $sale) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400">
                                        {{ $sale->receipt_no }}
                                    </a>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $sale->completed_at ? $sale->completed_at->format('Y-m-d H:i') : '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                    <ul class="list-disc list-inside space-y-0.5 text-xs">
                                        @foreach($sale->lines as $line)
                                            <li>{{ $line->product?->name ?? $line->product?->brand }} (×{{ $line->quantity }})</li>
                                        @endforeach
                                    </ul>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                    {{ $sale->totalEtb() }} ETB
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $sale->isCompleted() ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300' }}">
                                        {{ ucfirst($sale->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                    {{ __('customers.no_purchases_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Tab 4: Health Record Access Log (Audit trail) -->
    @if($activeTab === 'audit' && $canManage)
        <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-700">
                <h2 class="text-base font-semibold text-slate-900 dark:text-white">{{ __('customers.health_views_log_title') }}</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium">
                        <tr>
                            <th class="px-6 py-3.5">{{ __('customers.viewed_by') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.role') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.viewed_at') }}</th>
                            <th class="px-6 py-3.5">{{ __('customers.ip_address') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                        @forelse($healthViews as $view)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <td class="px-6 py-4 font-medium text-slate-900 dark:text-white">
                                    {{ $view->user?->name ?? 'User #'.$view->user_id }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300 text-xs font-mono">
                                    {{ $view->role_used ?? '—' }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $view->viewed_at ? $view->viewed_at->format('Y-m-d H:i:s') : '—' }}
                                </td>
                                <td class="px-6 py-4 font-mono text-xs text-slate-500">
                                    {{ $view->ip_address ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                    {{ __('customers.no_health_views_found') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

