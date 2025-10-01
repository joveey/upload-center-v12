<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-gray-50">
            @include('layouts.navigation')

            @isset($header)
                <header class="bg-white shadow-sm border-b border-gray-200">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>

            <!-- Footer (visible for all users) -->
            <footer class="bg-white border-t border-gray-200 mt-12">
                <div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- About -->
                        <div>
                            <div class="flex items-center space-x-3 mb-4">
                                <div class="bg-blue-600 p-2 rounded-lg">
                                    <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <span class="text-lg font-bold text-blue-600">
                                    Upload Center
                                </span>
                            </div>
                            <p class="text-sm text-gray-600">
                                Platform upload dan pengelolaan data Excel yang mudah dan efisien untuk organisasi Anda.
                            </p>
                        </div>

                        <!-- Quick Links -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Quick Links</h3>
                            <ul class="space-y-2">
                                @auth
                                <li><a href="{{ route('dashboard') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Dashboard</a></li>
                                @can('register format')
                                <li><a href="{{ route('mapping.register.form') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Register Format</a></li>
                                @endcan
                                <li><a href="{{ route('profile.edit') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Profile</a></li>
                                @else
                                <li><a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Login</a></li>
                                <li><a href="{{ route('register') }}" class="text-sm text-gray-600 hover:text-blue-600 transition-colors">Register</a></li>
                                @endauth
                            </ul>
                        </div>

                        <!-- Contact Info -->
                        <div>
                            <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wider mb-4">Contact</h3>
                            <ul class="space-y-2">
                                <li class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    support@uploadcenter.com
                                </li>
                                <li class="flex items-center text-sm text-gray-600">
                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                    </svg>
                                    +62 123 4567 890
                                </li>
                            </ul>
                        </div>
                    </div>

                    <div class="mt-8 pt-8 border-t border-gray-200">
                        <p class="text-center text-sm text-gray-500">
                            © {{ date('Y') }} Upload Center. All rights reserved.
                        </p>
                    </div>
                </div>
            </footer>
        </div>
        
        @stack('scripts')
    </body>
</html>