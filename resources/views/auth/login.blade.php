<x-guest-layout>
    <div class="mb-8 text-center">
        <div class="flex items-center justify-center mb-4">
            <img src="/images/panasonic-logo.svg" alt="Panasonic" class="h-14">
        </div>
        <p class="text-sm text-gray-600 font-semibold tracking-wide">Sign in to Your Account</p>
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-6">
        @csrf

        <div class="space-y-1">
            <x-input-label for="email" :value="__('Email')" class="text-gray-700 font-semibold" />
            <x-text-input id="email" class="block w-full px-4 py-3 border-gray-300 focus:border-[#0b57e3] focus:ring-2 focus:ring-[#0b57e3]/20 rounded-lg shadow-sm transition-all duration-150" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" placeholder="Email" />
            <x-input-error :messages="$errors->get('email')" class="mt-1" />
        </div>

        <div class="space-y-1">
            <x-input-label for="password" :value="__('Password')" class="text-gray-700 font-semibold" />
            <x-text-input id="password" class="block w-full px-4 py-3 border-gray-300 focus:border-[#0b57e3] focus:ring-2 focus:ring-[#0b57e3]/20 rounded-lg shadow-sm transition-all duration-150" type="password" name="password" required autocomplete="current-password" placeholder="Password" />
            <x-input-error :messages="$errors->get('password')" class="mt-1" />
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center cursor-pointer group">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-[#0057b7] shadow-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30 focus:ring-opacity-50 transition-all duration-200" name="remember">
                <span class="ml-2 text-sm text-gray-600 group-hover:text-gray-800 transition-colors duration-200">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-sm text-[#0057b7] hover:text-[#004a99] font-semibold hover:underline transition-all duration-200" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <div class="space-y-4">
            <button type="submit" class="w-full flex justify-center items-center px-6 py-3 bg-[#0b57e3] hover:bg-[#0a4dc9] border border-transparent rounded-lg font-semibold text-sm text-white transition-all duration-200 shadow-md">
                {{ __('Log In') }}
            </button>

        </div>
    </form>
</x-guest-layout>
