<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" type="image/svg+xml" href="{{ asset('images/panasonic-logo.svg') }}">
        <link rel="alternate icon" type="image/png" href="{{ asset('favicon.ico') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-[Inter] text-gray-900 antialiased">
        <div class="min-h-screen flex flex-col items-center justify-center bg-gradient-to-br from-[#1e63f5] via-[#1f70f7] to-[#1e63f5] px-4 py-8">
            <div class="w-full max-w-md bg-white shadow-2xl rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-8 py-10">
                    {{ $slot }}
                </div>
                <div class="px-8 pb-6 text-center text-xs text-gray-500">
                    © {{ date('Y') }} Upload Center
                </div>
            </div>
        </div>
    </body>
</html>
