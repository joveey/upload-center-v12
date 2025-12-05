<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 shadow-sm sticky top-0 z-40 py-4">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-20 items-center">
            <div class="flex items-center space-x-8">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ auth()->check() ? route('dashboard') : '/' }}" class="flex flex-col items-center justify-center leading-tight text-center">
                        <img src="/images/panasonic-logo.svg" alt="Panasonic" class="h-8">
                        <span class="text-[11px] font-semibold text-[#0c4fb2] mt-0.5 tracking-tight">Upload Center</span>
                    </a>
                </div>

                @auth
                <!-- Navigation Links (Authenticated) -->
                <div class="hidden space-x-6 sm:-my-px sm:flex">
                    <a href="{{ route('dashboard') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('dashboard') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        {{ __('Dashboard') }}
                    </a>
                     <!-- Format Link -->
                    <a href="{{ route('formats.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('formats.*') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        {{ __('Format') }}
                    </a>

                    <a href="{{ route('legacy.format.list') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('legacy.format.*') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h10"></path>
                        </svg>
                        {{ __('Legacy') }}
                    </a>

                    <a href="{{ route('logs.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('logs.*') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        {{ __('Logs') }}
                    </a>

                    @if(auth()->user()?->hasRole('super-admin'))
                        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.users.*') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2a3 3 0 00-.879-2.121M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2a3 3 0 01.879-2.121M12 12a5 5 0 100-10 5 5 0 000 10zm0 0c1.657 0 3 1.79 3 4v1m-3-5c-1.657 0-3 1.79-3 4v1" />
                            </svg>
                            {{ __('Users') }}
                        </a>
                    @endif

                    @can('register format')
                        <a href="{{ route('mapping.register.form') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('mapping.*') ? 'border-[#0057b7] text-[#0f172a]' : 'border-transparent text-gray-500 hover:text-[#0f172a] hover:border-[#c7d9f3]' }} text-sm font-medium leading-5 focus:outline-none focus:border-[#004a99] transition duration-150 ease-in-out">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            {{ __('Register Format') }}
                        </a>
                        
                    @endcan
                </div>
                @endauth
            </div>

            <!-- Right Side Navigation -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                @auth
                <!-- Division Badge -->
                @if(Auth::user()->division)
                    <div class="mr-4 px-3 py-1.5 bg-[#e8f1fb] rounded-full">
                        <span class="text-xs font-semibold text-[#004a99]">{{ Auth::user()->division->name }}</span>
                    </div>
                @endif

                <!-- User Dropdown -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-600 bg-white hover:bg-[#eef4fc] focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#0057b7] transition ease-in-out duration-150">
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full bg-[#0057b7] flex items-center justify-center mr-2">
                                    <span class="text-white font-semibold text-sm">{{ substr(Auth::user()->name, 0, 1) }}</span>
                                </div>
                                <span>{{ Auth::user()->name }}</span>
                                <svg class="ml-2 -mr-0.5 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
                            <p class="text-xs text-gray-500">{{ Auth::user()->email }}</p>
                            @if(Auth::user()->division)
                                <p class="text-xs text-[#0057b7] mt-1">📍 {{ Auth::user()->division->name }}</p>
                            @endif
                        </div>

                        <x-dropdown-link :href="route('profile.edit')">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                {{ __('Profile') }}
                            </div>
                        </x-dropdown-link>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                <div class="flex items-center text-red-600">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    {{ __('Log Out') }}
                                </div>
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
                @else
                <!-- Guest Navigation -->
                <div class="flex items-center space-x-3">
                    <a href="{{ route('login') }}" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-[#eef4fc] focus:outline-none focus:ring-2 focus:ring-[#0057b7] focus:ring-offset-2 transition-all duration-200">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                        </svg>
                        Masuk
                    </a>
                    <div class="inline-flex items-center px-4 py-2 bg-gray-100 border border-gray-200 rounded-lg font-semibold text-sm text-gray-600">
                        Registrasi ditutup
                    </div>
                </div>
                @endauth
            </div>

            <!-- Hamburger -->
            <div class="-mr-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        @auth
        <div class="pt-2 pb-3 space-y-1">
            <a href="{{ route('dashboard') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('dashboard') ? 'border-[#0057b7] text-[#004a99] bg-[#e8f1fb]' : 'border-transparent text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3]' }} text-base font-medium transition duration-150 ease-in-out">
                {{ __('Dashboard') }}
            </a>

            <a href="{{ route('legacy.format.list') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('legacy.format.*') ? 'border-[#0057b7] text-[#004a99] bg-[#e8f1fb]' : 'border-transparent text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3]' }} text-base font-medium transition duration-150 ease-in-out">
                {{ __('Legacy') }}
            </a>

            <a href="{{ route('logs.index') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('logs.*') ? 'border-[#0057b7] text-[#004a99] bg-[#e8f1fb]' : 'border-transparent text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3]' }} text-base font-medium transition duration-150 ease-in-out">
                {{ __('Logs') }}
            </a>

            @if(auth()->user()?->hasRole('super-admin'))
                <a href="{{ route('admin.users.index') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('admin.users.*') ? 'border-[#0057b7] text-[#004a99] bg-[#e8f1fb]' : 'border-transparent text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3]' }} text-base font-medium transition duration-150 ease-in-out">
                    {{ __('Users') }}
                </a>
            @endif

            @can('register format')
                <a href="{{ route('mapping.register.form') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('mapping.*') ? 'border-[#0057b7] text-[#004a99] bg-[#e8f1fb]' : 'border-transparent text-gray-600 hover:text-[#0f172a] hover:bg-[#eef4fc] hover:border-[#c7d9f3]' }} text-base font-medium transition duration-150 ease-in-out">
                    {{ __('Register Format') }}
                </a>
            @endcan
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                @if(Auth::user()->division)
                    <div class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-[#e8f1fb] text-[#004a99]">
                        {{ Auth::user()->division->name }}
                    </div>
                @endif
            </div>

            <div class="mt-3 space-y-1">
                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-base font-medium text-gray-500 hover:text-gray-800 hover:bg-gray-100 transition duration-150 ease-in-out">
                    {{ __('Profile') }}
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <a href="{{ route('logout') }}" onclick="event.preventDefault(); this.closest('form').submit();" class="block px-4 py-2 text-base font-medium text-red-600 hover:text-red-800 hover:bg-red-50 transition duration-150 ease-in-out">
                        {{ __('Log Out') }}
                    </a>
                </form>
            </div>
        </div>
        @else
        <!-- Guest Mobile Menu -->
        <div class="pt-2 pb-3 space-y-1 border-t border-gray-200">
            <a href="{{ route('login') }}" class="block pl-3 pr-4 py-2 border-l-4 border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 text-base font-medium transition duration-150 ease-in-out">
                <div class="flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                    </svg>
                    Masuk
                </div>
            </a>
            <div class="block pl-3 pr-4 py-2 border-l-4 border-gray-200 text-gray-600 bg-gray-50 text-base font-medium">
                Registrasi ditutup
            </div>
        </div>
        @endauth
    </div>
</nav>
