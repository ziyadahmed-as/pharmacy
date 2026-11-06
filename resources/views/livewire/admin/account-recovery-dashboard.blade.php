<div class="space-y-6">
    <!-- Header with Action Button -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">UC-36: Account Recovery & Identity Verification</h1>
            <p class="text-sm text-slate-500 mt-1">
                Administrative workstation for account unlocking, identity verification, and secure password resets.
            </p>
        </div>
        <div>
            <a href="{{ route('admin.account-recovery.create') }}" 
               class="inline-flex items-center px-4 py-2 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Initiate New Recovery
            </a>
        </div>
    </div>

    @if (session('error'))
        <div class="rounded-md bg-red-50 p-4 border border-red-200">
            <div class="flex">
                <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
                <div class="ml-3">
                    <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Grid: Locked Accounts & Recent Verifications -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Locked Accounts Table -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center">
                        <span class="w-2.5 h-2.5 rounded-full bg-red-500 mr-2"></span>
                        Currently Locked Accounts (UC-33 Lockouts)
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Accounts locked after 5 consecutive failed login attempts</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 font-semibold text-slate-600">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Locked At</th>
                            <th class="px-4 py-3">Failed Tries</th>
                            <th class="px-4 py-3 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($lockedAccounts as $account)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900">{{ $account->name }}</div>
                                    <div class="text-[11px] text-slate-400 font-mono">{{ $account->username }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ $account->locked_at?->diffForHumans() ?? 'Locked' }}
                                </td>
                                <td class="px-4 py-3 font-semibold text-red-600">
                                    {{ $account->failed_login_attempts }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('admin.account-recovery.create', ['userId' => $account->id]) }}" 
                                       class="inline-flex items-center px-2.5 py-1 text-xs font-medium rounded text-indigo-700 bg-indigo-50 hover:bg-indigo-100">
                                        Verify & Unlock
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                    No accounts currently locked. System security status nominal.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Identity Verifications -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900 flex items-center">
                        <span class="w-2.5 h-2.5 rounded-full bg-indigo-500 mr-2"></span>
                        Recent Identity Verifications
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">Mandatory IAM verification history before account actions</p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 font-semibold text-slate-600">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Method</th>
                            <th class="px-4 py-3">Outcome</th>
                            <th class="px-4 py-3">Verified By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($recentVerifications as $verification)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-mono font-medium text-slate-900">
                                    {{ $verification->user?->username ?? 'Unknown' }}
                                </td>
                                <td class="px-4 py-3 text-slate-500 capitalize">
                                    {{ str_replace('_', ' ', $verification->verification_method) }}
                                </td>
                                <td class="px-4 py-3">
                                    @if($verification->outcome === 'verified')
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-100 text-emerald-800">Verified</span>
                                    @else
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800">Refused / Failed</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-slate-500">
                                    {{ $verification->administrator?->username ?? 'Admin' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-6 text-center text-slate-400">
                                    No verification history recorded yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Audit Event History for UC-36 Operations -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
        <div class="p-5 border-b border-slate-100">
            <h2 class="text-base font-bold text-slate-900">UC-36 Security Audit Trail</h2>
            <p class="text-xs text-slate-500 mt-0.5">Immutable audit events recorded for unlocks, resets, and verification challenges</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead class="bg-slate-50 font-semibold text-slate-600">
                    <tr>
                        <th class="px-4 py-3">Timestamp</th>
                        <th class="px-4 py-3">Action</th>
                        <th class="px-4 py-3">Description</th>
                        <th class="px-4 py-3">Admin / Performer</th>
                        <th class="px-4 py-3">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-slate-700 font-mono">
                    @forelse($recoveryLogs as $log)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">
                                {{ $log->created_at->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="font-semibold text-indigo-700">{{ $log->action }}</span>
                            </td>
                            <td class="px-4 py-3 font-sans text-slate-800">
                                {{ $log->description }}
                            </td>
                            <td class="px-4 py-3 font-sans text-slate-600">
                                {{ $log->user?->username ?? 'System' }}
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $log->ip_address }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-slate-400 font-sans">
                                No recovery audit logs recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
