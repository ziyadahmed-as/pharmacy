<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'Noto Sans Ethiopic', 'ui-sans-serif', 'system-ui', 'sans-serif'] } } } }
    </script>
    @livewireStyles
</head>
<body class="h-full font-sans antialiased">
    <div class="relative min-h-full overflow-hidden bg-gradient-to-br from-teal-600 via-teal-700 to-teal-900">
        {{-- Background pattern --}}
        <div class="absolute inset-0 opacity-10" style="background-image: radial-gradient(circle at 1px 1px, white 1px, transparent 0); background-size: 32px 32px;"></div>

        {{-- Language switcher --}}
        <div class="absolute right-4 top-4 z-10">
            <x-language-switcher />
        </div>

        <div class="relative flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
            {{ $slot }}

            {{-- Footer --}}
            <p class="mt-8 text-center text-xs text-teal-200/70">
                &copy; {{ date('Y') }} {{ __('common.app_name') }}
            </p>
        </div>
    </div>
    @livewireScripts
</body>
</html>
