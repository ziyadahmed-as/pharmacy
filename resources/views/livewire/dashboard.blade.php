<div class="space-y-6">
    <!-- Welcome Header Card -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-900">Welcome, {{ $user->name }}</h1>
                <p class="text-sm text-slate-500 mt-0.5">
                    Username: <span class="font-mono text-slate-700 font-semibold">{{ $user->username }}</span> | 
                    Assigned Role: <span class="font-medium text-teal-700">{{ $user->roles->pluck('name')->join(', ') }}</span>
                </p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                    <span class="w-1.5 h-1.5 mr-1.5 rounded-full bg-emerald-500"></span> Active Session
                </span>
                <span class="text-xs text-slate-400">
                    Last Login: {{ $user->last_login_at?->format('Y-m-d H:i') ?? 'First Session' }} (IP: {{ $user->last_login_ip ?? '127.0.0.1' }})
                </span>
            </div>
        </div>
    </div>

    <!-- Role-Based Capability Panels -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <!-- 1. Pharmacy Operations (Staff, Manager, Owner) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-teal-50 text-teal-600 flex items-center justify-center font-bold">
                        RX
                    </div>
                    @if($user->canAccessBusinessData())
                        <span class="text-xs bg-emerald-100 text-emerald-700 font-medium px-2 py-0.5 rounded">Authorized</span>
                    @else
                        <span class="text-xs bg-red-100 text-red-700 font-medium px-2 py-0.5 rounded">Restricted</span>
                    @endif
                </div>
                <h3 class="font-semibold text-slate-900">Pharmacy Operations</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Dispensing, prescription intake, patient records, and point-of-sale operations.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                @if($user->canAccessBusinessData())
                    <span class="text-xs text-teal-600 font-medium">Accessible via frontline role</span>
                @else
                    <span class="text-xs text-slate-400 italic">System Administrator excluded by security policy</span>
                @endif
            </div>
        </div>

        <!-- 2. Pharmacy Management & Compliance (Manager, Owner) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold">
                        MGT
                    </div>
                    @if($user->hasAnyRole(['pharmacy_manager', 'owner']))
                        <span class="text-xs bg-emerald-100 text-emerald-700 font-medium px-2 py-0.5 rounded">Authorized</span>
                    @else
                        <span class="text-xs bg-slate-100 text-slate-500 font-medium px-2 py-0.5 rounded">No Access</span>
                    @endif
                </div>
                <h3 class="font-semibold text-slate-900">Operational Management</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Staff supervision, inventory control, purchase orders, and compliance reports.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                @if($user->hasAnyRole(['pharmacy_manager', 'owner']))
                    <span class="text-xs text-blue-600 font-medium">Supervisory authorization</span>
                @else
                    <span class="text-xs text-slate-400">Requires Manager or Owner role</span>
                @endif
            </div>
        </div>

        <!-- 3. System Administration (System Administrator Only) -->
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-5 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-3">
                    <div class="w-10 h-10 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold">
                        SYS
                    </div>
                    @can('manage-system')
                        <span class="text-xs bg-emerald-100 text-emerald-700 font-medium px-2 py-0.5 rounded">Authorized</span>
                    @else
                        <span class="text-xs bg-slate-100 text-slate-500 font-medium px-2 py-0.5 rounded">No Access</span>
                    @endcan
                </div>
                <h3 class="font-semibold text-slate-900">System Administration</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Technical configuration, account unlocks (UC-36), security auditing, and server health.
                </p>
            </div>
            <div class="mt-4 pt-3 border-t border-slate-100">
                @can('manage-system')
                    <span class="text-xs text-indigo-600 font-medium">Technical Administrator access</span>
                @else
                    <span class="text-xs text-slate-400">Restricted to technical role</span>
                @endif
            </div>
        </div>
    </div>

    <!-- Security & Audit Info -->
    <div class="bg-slate-50 rounded-xl border border-slate-200 p-5">
        <h4 class="text-xs font-semibold text-slate-700 uppercase tracking-wider mb-2">UC-33 Security Controls Active</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block">Session Inactivity Timeout</span>
                <span class="font-semibold text-slate-800">15 Minutes (Server-Enforced)</span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block">Lockout Protection</span>
                <span class="font-semibold text-slate-800">5 Consecutive Failures</span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block">Session Fixation Defense</span>
                <span class="font-semibold text-emerald-700">Regenerated on Login</span>
            </div>
            <div class="bg-white p-3 rounded-lg border border-slate-200">
                <span class="text-slate-400 block">Audit Logging</span>
                <span class="font-semibold text-emerald-700">Immutable Event Chain</span>
            </div>
        </div>
    </div>
</div>
