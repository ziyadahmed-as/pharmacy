<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('customers.title') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('customers.subtitle') }}</p>
        </div>

        @if($canManage)
            <div>
                <a href="{{ route('customers.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    {{ __('customers.new_customer') }}
                </a>
            </div>
        @endif
    </div>

    <!-- Search Bar -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-4">
        <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </div>
            <input
                type="text"
                wire:model.live.debounce.300ms="search"
                placeholder="{{ __('customers.search_placeholder') }}"
                class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:border-teal-500"
            />
        </div>
    </div>

    <!-- Customers Table -->
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700 text-left text-sm">
                <thead class="bg-slate-50 dark:bg-slate-900 text-slate-600 dark:text-slate-300 font-medium">
                    <tr>
                        <th class="px-6 py-3.5">{{ __('customers.name') }}</th>
                        <th class="px-6 py-3.5">{{ __('customers.phone') }}</th>
                        <th class="px-6 py-3.5">{{ __('customers.address') }}</th>
                        <th class="px-6 py-3.5">{{ __('customers.birth_date') }}</th>
                        <th class="px-6 py-3.5">{{ __('customers.consent_status') }}</th>
                        <th class="px-6 py-3.5 text-right">{{ __('customers.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                    @forelse($customers as $customer)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                            <td class="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                                <a href="{{ route('customers.show', $customer) }}" class="hover:text-teal-600 transition">
                                    {{ $customer->name }}
                                </a>
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300 font-mono text-xs">
                                {{ $customer->phone }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $customer->address ?? '—' }}
                            </td>
                            <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                {{ $customer->birth_date ? $customer->birth_date->format('Y-m-d') : '—' }}
                            </td>
                            <td class="px-6 py-4">
                                @if($customer->consent_given)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                                        ✓ {{ __('customers.consent_given') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                        {{ __('customers.no_consent') }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-right space-x-3">
                                <a href="{{ route('customers.show', $customer) }}" class="text-teal-600 hover:text-teal-800 dark:text-teal-400 font-medium text-xs">
                                    {{ __('customers.view_profile') }}
                                </a>
                                @if($canManage)
                                    <a href="{{ route('customers.edit', $customer) }}" class="text-slate-600 hover:text-slate-800 dark:text-slate-400 font-medium text-xs">
                                        {{ __('customers.edit_profile') }}
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-slate-400 dark:text-slate-500">
                                {{ __('customers.no_customers_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($customers->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-700">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
</div>

