<div class="max-w-3xl mx-auto space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">
                {{ $customer ? __('customers.edit_profile') : __('customers.new_customer') }}
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('customers.subtitle') }}</p>
        </div>
        <a href="{{ route('customers.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
            {{ __('customers.back_to_list') }}
        </a>
    </div>

    @if($errorMessage)
        <div class="p-4 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 text-sm text-rose-800 dark:text-rose-200">
            {{ $errorMessage }}
        </div>
    @endif

    <form wire:submit="save" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 space-y-6">
        <!-- Basic Information -->
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-700 pb-2">
                {{ __('customers.profile_tab') }}
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.name') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model="name"
                        required
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    />
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.phone') }} <span class="text-rose-500">*</span>
                    </label>
                    <input
                        type="text"
                        wire:model.live.debounce.400ms="phone"
                        required
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white font-mono focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    />
                    @if($duplicateWarning)
                        <p class="mt-1 text-xs text-amber-600 dark:text-amber-400 font-medium flex items-center gap-1">
                            <svg class="h-4 w-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            {{ $duplicateWarning }}
                        </p>
                    @endif
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.birth_date') }}
                    </label>
                    <input
                        type="date"
                        wire:model="birth_date"
                        max="{{ now()->toDateString() }}"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    />
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.address') }}
                    </label>
                    <input
                        type="text"
                        wire:model="address"
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    />
                </div>
            </div>
        </div>

        <!-- Health & Consent Section (FR-5.6, AC-26) -->
        <div class="space-y-4 pt-4 border-t border-slate-200 dark:border-slate-700">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-semibold text-slate-900 dark:text-white">
                        {{ __('customers.health_section_title') }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('customers.consent_required_notice') }}
                    </p>
                </div>
            </div>

            <!-- Consent Toggle -->
            <div class="p-4 rounded-lg bg-teal-50 dark:bg-teal-950/30 border border-teal-200 dark:border-teal-800 flex items-start gap-3">
                <input
                    type="checkbox"
                    id="consent_toggle"
                    wire:model.live="consent_given"
                    class="mt-1 h-4 w-4 text-teal-600 border-slate-300 rounded focus:ring-teal-500 cursor-pointer"
                />
                <label for="consent_toggle" class="text-sm font-medium text-slate-900 dark:text-white cursor-pointer select-none">
                    {{ __('customers.consent_toggle_label') }}
                    <span class="block text-xs font-normal text-slate-500 dark:text-slate-400 mt-0.5">
                        {{ __('customers.health_fields_restricted') }}
                    </span>
                </label>
            </div>

            <div class="grid grid-cols-1 gap-4 @if(!$consent_given) opacity-50 pointer-events-none @endif">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.allergies_label') }}
                    </label>
                    <textarea
                        wire:model="allergies"
                        rows="2"
                        placeholder="{{ __('customers.allergies_placeholder') }}"
                        @if(!$consent_given) disabled @endif
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    ></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-slate-600 dark:text-slate-300 mb-1">
                        {{ __('customers.chronic_conditions_label') }}
                    </label>
                    <textarea
                        wire:model="chronic_conditions"
                        rows="2"
                        placeholder="{{ __('customers.chronic_conditions_placeholder') }}"
                        @if(!$consent_given) disabled @endif
                        class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-600 rounded-lg text-sm text-slate-900 dark:text-white focus:ring-2 focus:ring-teal-500 focus:outline-none"
                    ></textarea>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
            <a href="{{ route('customers.index') }}" class="px-4 py-2 border border-slate-300 dark:border-slate-600 rounded-lg text-sm font-medium text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                {{ __('common.cancel') }}
            </a>
            <button
                type="submit"
                class="px-5 py-2 rounded-lg text-sm font-medium text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition shadow-sm"
            >
                {{ __('customers.save_customer') }}
            </button>
        </div>
    </form>
</div>

