<div class="sm:mx-auto sm:w-full sm:max-w-md">
    <!-- Pharmacy Logo & Header -->
    <div class="flex justify-center">
        <div class="w-14 h-14 rounded-xl bg-teal-600 flex items-center justify-center text-white shadow-lg">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
            </svg>
        </div>
    </div>
    <h2 class="mt-4 text-center text-2xl font-bold tracking-tight text-slate-900">
        Pharmacy Management System
    </h2>
    <p class="mt-1 text-center text-sm text-slate-500">
        Sign in to your authorized staff account
    </p>
</div>

<div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
    <div class="bg-white py-8 px-4 shadow-xl sm:rounded-xl sm:px-10 border border-slate-100">
        <!-- Validation Error Alert -->
        @if ($errors->has('username'))
            <div class="mb-5 rounded-lg bg-red-50 p-4 border border-red-200" role="alert">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-500" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Authentication Failed</h3>
                        <div class="mt-1 text-sm text-red-700">
                            {{ $errors->first('username') }}
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <form wire:submit="login" class="space-y-6">
            <!-- Username Input -->
            <div>
                <label for="username" class="block text-sm font-medium text-slate-700">
                    Username
                </label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <input wire:model="username" 
                           id="username" 
                           name="username" 
                           type="text" 
                           autocomplete="username" 
                           required 
                           class="block w-full rounded-lg border-slate-300 px-3 py-2 text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-teal-500 sm:text-sm border"
                           placeholder="Enter your PMS username">
                </div>
                @error('username')
                    @if ($message !== $errors->first('username'))
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @endif
                @enderror
            </div>

            <!-- Password Input with Show/Hide Toggle -->
            <div>
                <label for="password" class="block text-sm font-medium text-slate-700">
                    Password
                </label>
                <div class="mt-1 relative rounded-md shadow-sm">
                    <input wire:model="password" 
                           id="password" 
                           name="password" 
                           type="{{ $showPassword ? 'text' : 'password' }}" 
                           autocomplete="current-password" 
                           required 
                           class="block w-full rounded-lg border-slate-300 pr-10 pl-3 py-2 text-slate-900 placeholder-slate-400 focus:border-teal-500 focus:outline-none focus:ring-teal-500 sm:text-sm border"
                           placeholder="••••••••">
                    
                    <button type="button" 
                            wire:click="togglePasswordVisibility"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600 focus:outline-none"
                            title="{{ $showPassword ? 'Hide password' : 'Show password' }}">
                        @if ($showPassword)
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                            </svg>
                        @else
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        @endif
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember Me & Security Policy Note -->
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input wire:model="remember" 
                           id="remember" 
                           name="remember" 
                           type="checkbox" 
                           class="h-4 w-4 rounded border-slate-300 text-teal-600 focus:ring-teal-500">
                    <label for="remember" class="ml-2 block text-xs text-slate-600">
                        Remember this terminal
                    </label>
                </div>

                <div class="text-xs text-slate-500">
                    <span>Locked after 5 failed attempts</span>
                </div>
            </div>

            <!-- Submit Button with Loading Indicator -->
            <div>
                <button type="submit" 
                        wire:loading.attr="disabled"
                        class="w-full flex justify-center items-center py-2.5 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold text-white bg-teal-600 hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 disabled:opacity-50 transition">
                    <span wire:loading.remove>Sign In</span>
                    <span wire:loading class="flex items-center space-x-2">
                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Authenticating...</span>
                    </span>
                </button>
            </div>
        </form>

        <!-- Help / Reset Support Notice (UC-36 Pointer) -->
        <div class="mt-6 border-t border-slate-200 pt-4 text-center">
            <p class="text-xs text-slate-500">
                Forgotten your password or locked out? 
                <span class="font-medium text-slate-700">Contact your System Administrator (UC-36).</span>
            </p>
        </div>
    </div>
</div>
