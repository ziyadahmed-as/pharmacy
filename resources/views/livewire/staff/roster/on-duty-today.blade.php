<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-5 border border-gray-200 dark:border-gray-700">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-bold text-gray-900 dark:text-white flex items-center">
            <svg class="mr-2 h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
            </svg>
            {{ __('roster.on_duty_today') }}
        </h3>
        <a href="{{ route('roster.index') }}" class="text-xs text-primary-600 hover:text-primary-700 dark:text-primary-400 font-medium">
            {{ __('roster.title') }} &rarr;
        </a>
    </div>

    @if ($onDuty->isNotEmpty())
        <div class="divide-y divide-gray-100 dark:divide-gray-700/50">
            @foreach ($onDuty as $entry)
                <div class="py-2.5 flex items-center justify-between text-xs">
                    <div>
                        <div class="font-semibold text-gray-900 dark:text-white">{{ $entry->staff->name }}</div>
                        <div class="text-gray-500 dark:text-gray-400">{{ $entry->formatted_start_time }} &ndash; {{ $entry->formatted_end_time }}</div>
                    </div>
                    <div>
                        @if ($entry->till)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200">
                                {{ $entry->till->name }}
                            </span>
                        @else
                            <span class="text-gray-400 text-[11px]">{{ __('roster.no_till') }}</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="text-xs text-gray-500 dark:text-gray-400 py-3 text-center italic">
            {{ __('roster.no_one_on_duty') }}
        </p>
    @endif
</div>

