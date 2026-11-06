<div class="max-w-4xl mx-auto space-y-6">
    <!-- Progress Stepper -->
    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-4">
        <nav aria-label="Progress">
            <ol class="flex items-center justify-between text-xs font-medium">
                <li class="flex items-center {{ $step >= 1 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center mr-2 {{ $step >= 1 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600' }}">1</span>
                    Select Account
                </li>
                <li class="h-0.5 w-12 bg-slate-200"></li>
                <li class="flex items-center {{ $step >= 2 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center mr-2 {{ $step >= 2 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600' }}">2</span>
                    Identity Verification
                </li>
                <li class="h-0.5 w-12 bg-slate-200"></li>
                <li class="flex items-center {{ $step >= 3 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center mr-2 {{ $step >= 3 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600' }}">3</span>
                    Authorized Operation
                </li>
                <li class="h-0.5 w-12 bg-slate-200"></li>
                <li class="flex items-center {{ $step >= 4 ? 'text-indigo-600 font-bold' : 'text-slate-400' }}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center mr-2 {{ $step >= 4 ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-600' }}">4</span>
                    Credential Delivery
                </li>
            </ol>
        </nav>
    </div>

    <!-- Error Alert -->
    @error('action')
        <div class="rounded-md bg-red-50 p-4 border border-red-200">
            <p class="text-sm font-medium text-red-800">{{ $message }}</p>
        </div>
    @enderror

    <!-- STEP 1: SELECT ACCOUNT -->
    @if ($step === 1)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
            <div>
                <h2 class="text-lg font-bold text-slate-900">Step 1: Select User Account for Recovery</h2>
                <p class="text-xs text-slate-500">Search by username or staff display name to locate the account needing assistance.</p>
            </div>

            <div>
                <input wire:model.live.debounce.300ms="search" 
                       type="text" 
                       placeholder="Type username or name (minimum 2 characters)..." 
                       class="w-full rounded-lg border-slate-300 p-2.5 text-sm border focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div class="mt-4 divide-y divide-slate-100">
                @forelse($searchResults as $user)
                    <div class="py-3 flex items-center justify-between hover:bg-slate-50 px-3 rounded-lg transition">
                        <div>
                            <div class="text-sm font-bold text-slate-900">{{ $user->name }}</div>
                            <div class="text-xs text-slate-500 font-mono">Username: {{ $user->username }}</div>
                            <div class="text-[11px] mt-1">
                                Status: 
                                @if($user->status === 'active')
                                    <span class="text-emerald-700 bg-emerald-100 px-1.5 py-0.5 rounded font-semibold">Active</span>
                                @elseif($user->status === 'locked')
                                    <span class="text-red-700 bg-red-100 px-1.5 py-0.5 rounded font-semibold">Locked ({{ $user->failed_login_attempts }} fails)</span>
                                @elseif($user->status === 'deactivated')
                                    <span class="text-slate-600 bg-slate-200 px-1.5 py-0.5 rounded font-semibold">Deactivated (Cannot Recover)</span>
                                @else
                                    <span class="text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded font-semibold">{{ $user->status }}</span>
                                @endif
                            </div>
                        </div>

                        <div>
                            @if($user->status === 'deactivated')
                                <button disabled class="px-3 py-1.5 text-xs font-semibold bg-slate-100 text-slate-400 rounded-lg cursor-not-allowed">
                                    Deactivated
                                </button>
                            @else
                                <button wire:click="selectUser({{ $user->id }})" class="px-3 py-1.5 text-xs font-semibold bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                    Select Account
                                </button>
                            @endif
                        </div>
                    </div>
                @empty
                    @if(strlen($search) >= 2)
                        <div class="text-center py-6 text-sm text-slate-400">
                            No matching user accounts found for "{{ $search }}".
                        </div>
                    @else
                        <div class="text-center py-6 text-xs text-slate-400">
                            Enter at least 2 characters to search for users.
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    @endif

    <!-- STEP 2: MANDATORY IDENTITY VERIFICATION -->
    @if ($step === 2)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-indigo-600">Step 2 of 4</span>
                <h2 class="text-lg font-bold text-slate-900 mt-1">Mandatory Identity Verification</h2>
                <p class="text-xs text-slate-500">
                    Target User: <strong class="text-slate-900">{{ $targetUser->name }}</strong> (Username: <code class="font-mono text-indigo-700">{{ $targetUser->username }}</code> | Status: <span class="capitalize font-semibold">{{ $targetUser->status }}</span>)
                </p>
            </div>

            <div class="rounded-lg bg-amber-50 p-4 border border-amber-200 text-xs text-amber-800">
                <strong>IAM Compliance Requirement:</strong> System Administrators must verify the identity of the person requesting assistance before unlocking an account or generating temporary credentials. If identity cannot be confirmed, the request must be refused.
            </div>

            <form wire:submit="submitIdentityVerification" class="space-y-4">
                <div>
                    <label for="verification_method" class="block text-xs font-semibold text-slate-700 uppercase">Verification Channel</label>
                    <select wire:model="verification_method" id="verification_method" class="mt-1 block w-full rounded-md border-slate-300 text-sm border p-2">
                        <option value="in_person">In-Person Visual / ID Badge Verification</option>
                        <option value="registered_phone">Voice Call to Registered Corporate Contact Phone</option>
                        <option value="approved_contact_channel">Approved Internal Contact Channel / Secure Dispatch</option>
                    </select>
                </div>

                <div>
                    <label for="verification_outcome" class="block text-xs font-semibold text-slate-700 uppercase">Verification Outcome</label>
                    <select wire:model="verification_outcome" id="verification_outcome" class="mt-1 block w-full rounded-md border-slate-300 text-sm border p-2 font-semibold">
                        <option value="verified" class="text-emerald-700">Identity Successfully Confirmed (Verified)</option>
                        <option value="failed" class="text-red-700">Identity Could Not Be Confirmed (Refuse Action)</option>
                    </select>
                </div>

                <div>
                    <label for="verification_notes" class="block text-xs font-semibold text-slate-700 uppercase">Non-Sensitive Context Notes</label>
                    <input wire:model="verification_notes" type="text" id="verification_notes" placeholder="e.g. Verified via pharmacy floor supervisory desk" class="mt-1 block w-full rounded-md border-slate-300 text-sm border p-2">
                    <p class="text-[11px] text-slate-400 mt-1">Do not record personal identification numbers or sensitive data in notes.</p>
                </div>

                <div class="flex justify-between items-center pt-4 border-t border-slate-100">
                    <button type="button" wire:click="$set('step', 1)" class="px-4 py-2 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                        Back to Search
                    </button>
                    <button type="submit" class="px-5 py-2 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                        Record Verification & Proceed
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- STEP 3: SELECT AUTHORIZED OPERATION -->
    @if ($step === 3)
        <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-6">
            <div class="border-b border-slate-100 pb-4">
                <span class="text-xs font-bold uppercase tracking-wider text-emerald-600">Identity Confirmed</span>
                <h2 class="text-lg font-bold text-slate-900 mt-1">Step 3: Select Recovery Operation</h2>
                <p class="text-xs text-slate-500">
                    Account: <strong class="text-slate-900">{{ $targetUser->username }}</strong> (Current Status: <span class="capitalize font-semibold text-indigo-700">{{ $targetUser->status }}</span>)
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Option 1: Unlock Account Only -->
                <div class="p-5 border rounded-xl flex flex-col justify-between {{ $targetUser->isEligibleForUnlock() ? 'border-indigo-200 bg-indigo-50/30' : 'border-slate-200 bg-slate-50 opacity-60' }}">
                    <div>
                        <div class="w-8 h-8 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold mb-3">
                            1
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm">Unlock Account Only</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Clears the 5-failure lockout flag and resets the counter to 0. Preserves the user's existing password.
                        </p>
                    </div>
                    <div class="mt-5">
                        @if ($targetUser->isEligibleForUnlock())
                            <button wire:click="unlockAccountOnly" class="w-full py-2 px-3 text-xs font-semibold text-white bg-indigo-600 rounded-lg hover:bg-indigo-700">
                                Unlock Account
                            </button>
                        @else
                            <button disabled class="w-full py-2 px-3 text-xs font-medium text-slate-400 bg-slate-200 rounded-lg cursor-not-allowed">
                                Not Currently Locked
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Option 2: Reset Password Only -->
                <div class="p-5 border rounded-xl flex flex-col justify-between {{ $targetUser->isEligibleForPasswordReset() ? 'border-teal-200 bg-teal-50/30' : 'border-slate-200 bg-slate-50 opacity-60' }}">
                    <div>
                        <div class="w-8 h-8 rounded-lg bg-teal-100 text-teal-700 flex items-center justify-center font-bold mb-3">
                            2
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm">Reset Password Only</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Generates a cryptographically random temporary password, invalidates all sessions, and enforces password change at next login.
                        </p>
                    </div>
                    <div class="mt-5">
                        @if ($targetUser->isEligibleForPasswordReset())
                            <button wire:click="resetPasswordOnly" class="w-full py-2 px-3 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-700 rounded-lg">
                                Generate Temp Password
                            </button>
                        @else
                            <button disabled class="w-full py-2 px-3 text-xs font-medium text-slate-400 bg-slate-200 rounded-lg cursor-not-allowed">
                                Ineligible for Reset
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Option 3: Combined Reset and Unlock -->
                <div class="p-5 border rounded-xl flex flex-col justify-between {{ $targetUser->isEligibleForUnlock() ? 'border-purple-200 bg-purple-50/30' : 'border-slate-200 bg-slate-50 opacity-60' }}">
                    <div>
                        <div class="w-8 h-8 rounded-lg bg-purple-100 text-purple-700 flex items-center justify-center font-bold mb-3">
                            3
                        </div>
                        <h3 class="font-bold text-slate-900 text-sm">Reset & Unlock Combined</h3>
                        <p class="text-xs text-slate-500 mt-1">
                            Atomically clears the lockout state, resets failed attempts, generates temporary credentials, and invalidates sessions.
                        </p>
                    </div>
                    <div class="mt-5">
                        @if ($targetUser->isEligibleForUnlock())
                            <button wire:click="resetPasswordAndUnlockCombined" class="w-full py-2 px-3 text-xs font-semibold text-white bg-purple-600 hover:bg-purple-700 rounded-lg">
                                Reset & Unlock
                            </button>
                        @else
                            <button disabled class="w-full py-2 px-3 text-xs font-medium text-slate-400 bg-slate-200 rounded-lg cursor-not-allowed">
                                Only For Locked Accounts
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-100 flex justify-start">
                <button type="button" wire:click="$set('step', 2)" class="px-4 py-2 text-xs font-medium text-slate-600 bg-slate-100 rounded-lg hover:bg-slate-200">
                    Back to Verification Form
                </button>
            </div>
        </div>
    @endif

    <!-- STEP 4: ONE-TIME CREDENTIAL DELIVERY REVEAL -->
    @if ($step === 4)
        <div class="bg-white rounded-xl shadow-lg border border-teal-200 p-8 text-center space-y-6">
            <div class="w-14 h-14 rounded-full bg-teal-100 text-teal-700 mx-auto flex items-center justify-center">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>

            <div>
                <h2 class="text-xl font-bold text-slate-900">One-Time Temporary Credential Generated</h2>
                <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                    Secure temporary password for user <strong class="text-slate-800">{{ $targetUser->username }}</strong>. Deliver this securely to the verified individual.
                </p>
            </div>

            <!-- Single-Reveal Box -->
            <div class="max-w-md mx-auto bg-slate-900 text-white rounded-xl p-5 shadow-inner">
                <span class="text-[10px] uppercase font-bold text-teal-400 tracking-widest block mb-1">Temporary Password (Single Reveal)</span>
                <span class="font-mono text-xl tracking-wider select-all text-amber-300 font-bold">{{ $oneTimePassword }}</span>
                <p class="text-[11px] text-slate-400 mt-2">
                    This password is not stored in plaintext and cannot be retrieved again after leaving this screen. The user will be forced to change it immediately upon authentication.
                </p>
            </div>

            <div>
                <button wire:click="finish" class="px-6 py-2.5 text-xs font-semibold text-white bg-slate-900 rounded-lg hover:bg-slate-800 transition">
                    Done & Return to Recovery Dashboard
                </button>
            </div>
        </div>
    @endif
</div>

