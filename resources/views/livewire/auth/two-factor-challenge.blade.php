<div class="sm:mx-auto sm:w-full sm:max-w-md">
    <div class="flex justify-center">
        <div class="w-14 h-14 rounded-xl bg-indigo-600 flex items-center justify-center text-white shadow-lg">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
            </svg>
        </div>
    </div>
    <h2 class="mt-4 text-center text-2xl font-bold tracking-tight text-slate-900">
        Two-Factor Verification
    </h2>
    <p class="mt-1 text-center text-sm text-slate-500">
        High-privilege role verification for {{ $pendingUser?->name ?? 'User' }}
    </p>
</div>

<div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
    <div class="bg-white py-8 px-4 shadow-xl sm:rounded-xl sm:px-10 border border-slate-100">
        <div class="mb-5 rounded-lg bg-indigo-50 p-4 border border-indigo-200">
            <div class="flex">
                <svg class="h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-3">
                    <p class="text-xs text-indigo-800">
                        Second-factor authentication is required for Pharmacy Managers and System Administrators. Please enter your 6-digit TOTP code.
                    </p>
                </div>
            </div>
        </div>

        <form wire:submit="verify" class="space-y-6">
            <div>
                <label for="code" class="block text-sm font-medium text-slate-700 text-center">
                    6-Digit Security Code
                </label>
                <div class="mt-2">
                    <input wire:model="code" 
                           id="code" 
                           name="code" 
                           type="text" 
                           maxlength="6" 
                           autofocus
                           required 
                           class="block w-full text-center tracking-widest text-2xl font-mono rounded-lg border-slate-300 px-3 py-2 text-slate-900 placeholder-slate-300 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 border"
                           placeholder="000000">
                </div>
                @error('code')
                    <p class="mt-2 text-center text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="space-y-3">
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 transition">
                    <span wire:loading.remove>Verify & Continue</span>
                    <span wire:loading>Verifying...</span>
                </button>

                <button type="button" 
                        wire:click="cancel"
                        class="w-full flex justify-center py-2 px-4 border border-slate-300 rounded-lg shadow-sm text-xs font-medium text-slate-700 bg-white hover:bg-slate-50 focus:outline-none">
                    Cancel & Return to Login
                </button>
            </div>
        </form>
    </div>
</div>
