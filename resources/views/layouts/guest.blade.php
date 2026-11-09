<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('common.app_name') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'Noto Sans Ethiopic', 'ui-sans-serif', 'system-ui', 'sans-serif'] } } } }
    </script>
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-slate-800">
    <div class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="absolute right-4 top-4"><x-language-switcher /></div>
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
