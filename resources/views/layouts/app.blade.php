<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Pharmacy Management System' }}</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdfa',
                            100: '#ccfbf1',
                            500: '#14b8a6',
                            600: '#0d9488',
                            700: '#0f766e',
                            800: '#115e59',
                            900: '#134e4a',
                        }
                    }
                }
            }
        }
    </script>
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-slate-800">
    <div class="min-h-full flex flex-col">
        <!-- Top Navigation Bar -->
        <header class="bg-white border-b border-slate-200 sticky top-0 z-30 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <!-- Brand / Logo -->
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-teal-600 flex items-center justify-center text-white shadow-md">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                        </div>
                        <div>
                            <span class="text-xl font-bold tracking-tight text-slate-900">PMS <span class="text-teal-600 font-semibold text-sm">Enterprise</span></span>
                            <span class="hidden sm:inline-block text-xs text-slate-400 ml-2">Pharmacy Operations Platform</span>
                        </div>
                    </div>

                    <!-- Role-Aware Navigation Links -->
                    @if(auth()->check())
                        <nav class="hidden md:flex space-x-6 items-center">
                            <a href="{{ route('dashboard') }}" class="text-sm font-medium {{ request()->routeIs('dashboard') ? 'text-teal-600 font-semibold' : 'text-slate-600 hover:text-slate-900' }}">
                                Dashboard
                            </a>

                            @if(auth()->user()->canAccessBusinessData())
                                <span class="text-xs bg-emerald-100 text-emerald-800 font-medium px-2 py-0.5 rounded-full" title="Authorized for clinical and operational modules">
                                    Pharmacy Operations
                                </span>
                            @endif

                            @can('manage-system')
                                <a href="{{ route('admin.account-recovery') }}" class="text-sm font-medium {{ request()->routeIs('admin.account-recovery*') ? 'text-indigo-600 font-semibold' : 'text-slate-600 hover:text-slate-900' }}">
                                    Account Recovery (UC-36)
                                </a>

                                <span class="text-xs bg-indigo-100 text-indigo-800 font-medium px-2 py-0.5 rounded-full" title="System Administration scope">
                                    System Administration
                                </span>
                            @endcan
                        </nav>

                        <!-- User Information & Logout -->
                        <div class="flex items-center space-x-4">
                            <div class="text-right">
                                <div class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</div>
                                <div class="text-xs text-slate-500 flex items-center justify-end space-x-1">
                                    <span class="inline-block w-2 h-2 rounded-full bg-emerald-500"></span>
                                    <span>
                                        {{ auth()->user()->roles->pluck('name')->join(', ') ?: 'No Role' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Logout Form -->
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" 
                                        class="inline-flex items-center px-3 py-1.5 border border-slate-300 shadow-sm text-xs font-medium rounded-md text-slate-700 bg-white hover:bg-slate-50 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 transition"
                                        title="Sign out of your session">
                                    <svg class="w-4 h-4 mr-1 text-slate-400 group-hover:text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Log out
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            @if (session('status'))
                <div class="mb-6 rounded-md bg-emerald-50 p-4 border border-emerald-200">
                    <div class="flex">
                        <svg class="h-5 w-5 text-emerald-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                        <div class="ml-3">
                            <p class="text-sm font-medium text-emerald-800">{{ session('status') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if(isset($slot))
                {{ $slot }}
            @else
                @yield('content')
            @endif
        </main>

        <!-- Footer -->
        <footer class="bg-white border-t border-slate-200 py-4 mt-auto">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-xs text-slate-400 flex flex-col sm:flex-row justify-between items-center space-y-2 sm:space-y-0">
                <div>Pharmacy Management System (PMS) • Standard v1.1</div>
                <div>Session Security: Server-Enforced Inactivity Timeout (15 mins)</div>
            </div>
        </footer>
    </div>

    @livewireScripts
</body>
</html>
