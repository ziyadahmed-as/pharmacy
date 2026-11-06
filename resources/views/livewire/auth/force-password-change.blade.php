<div class="max-w-md mx-auto my-8">
    <div class="bg-white py-8 px-6 shadow-md rounded-xl border border-slate-200">
        <div class="text-center mb-6">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-amber-100 mb-3">
                <svg class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-slate-900">Forced Password Change</h2>
            <p class="text-xs text-slate-500 mt-1">
                Your temporary password or administrator reset requires you to set a permanent secure password before accessing PMS modules.
            </p>
        </div>

        <form wire:submit="updatePassword" class="space-y-5">
            <div>
                <label for="current_password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    Current (Temporary) Password
                </label>
                <input wire:model="current_password" 
                       type="password" 
                       id="current_password" 
                       required 
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm border p-2">
                @error('current_password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    New Password
                </label>
                <input wire:model="password" 
                       type="password" 
                       id="password" 
                       required 
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm border p-2">
                <p class="mt-1 text-[11px] text-slate-400">Must be at least 8 characters, containing letters and numbers.</p>
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="password_confirmation" class="block text-xs font-semibold uppercase tracking-wider text-slate-700">
                    Confirm New Password
                </label>
                <input wire:model="password_confirmation" 
                       type="password" 
                       id="password_confirmation" 
                       required 
                       class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-teal-500 focus:ring-teal-500 text-sm border p-2">
            </div>

            <div>
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition">
                    <span wire:loading.remove>Update Password & Access System</span>
                    <span wire:loading>Updating...</span>
                </button>
            </div>
        </form>
    </div>
</div>
