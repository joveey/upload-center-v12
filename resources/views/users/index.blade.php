<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-xl bg-[#0057b7] flex items-center justify-center text-white shadow-sm">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2a3 3 0 00-.879-2.121M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2a3 3 0 01.879-2.121M12 12a5 5 0 100-10 5 5 0 000 10zm0 0c1.657 0 3 1.79 3 4v1m-3-5c-1.657 0-3 1.79-3 4v1" />
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                        Kelola Pengguna
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Registrasi publik dikunci. Admin membuat akun manual untuk setiap user.
                    </p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg shadow-sm flex items-center">
                    <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if ($errors->any())
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-sm">
                    <p class="font-semibold">Periksa kembali input yang diberikan.</p>
                </div>
            @endif

            <div class="bg-white shadow-lg rounded-2xl border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-[#0057b7] to-[#0072ce] px-6 py-4 border-b border-[#004a99] flex items-center justify-between">
                    <div>
                        <p class="text-lg font-semibold text-white">Buat User Baru</p>
                    </div>
                    <span class="text-xs px-3 py-1 rounded-full bg-white/15 text-white font-semibold border border-white/30">
                        Role otomatis mengikuti divisi
                    </span>
                </div>
                <div class="p-6">
                    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-5">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <x-input-label for="name" value="Nama Lengkap" />
                                <x-text-input id="name" name="name" type="text" class="block w-full" required autofocus :value="old('name')" placeholder="Nama user" />
                                <x-input-error :messages="$errors->get('name')" />
                            </div>

                            <div class="space-y-2">
                                <x-input-label for="email" value="Email" />
                                <x-text-input id="email" name="email" type="email" class="block w-full" required :value="old('email')" placeholder="email@perusahaan.com" />
                                <x-input-error :messages="$errors->get('email')" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                            <div class="space-y-2">
                                <x-input-label for="division_id" value="Divisi" />
                                <select id="division_id" name="division_id" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30 focus:ring-opacity-50" required>
                                    <option value="">Pilih divisi</option>
                                    @foreach ($divisions as $division)
                                        <option value="{{ $division->id }}" @selected(old('division_id') == $division->id)>
                                            {{ $division->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('division_id')" />
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                                <div class="space-y-2">
                                    <x-input-label for="password" value="Password" />
                                    <x-text-input id="password" name="password" type="password" class="block w-full" required autocomplete="new-password" />
                                    <x-input-error :messages="$errors->get('password')" />
                                </div>
                                <div class="space-y-2">
                                    <x-input-label for="password_confirmation" value="Konfirmasi Password" />
                                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="block w-full" required autocomplete="new-password" />
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between bg-[#f7faff] border border-[#dbe7fb] rounded-xl px-4 py-3">
                            <div>
                                <p class="text-sm font-semibold text-gray-800">Catatan</p>
                                <p class="text-xs text-gray-600">User akan langsung aktif setelah dibuat. Mereka dapat mengganti password lewat menu profil.</p>
                            </div>
                            <button type="submit" class="inline-flex items-center px-6 py-3 bg-[#0057b7] hover:bg-[#004a99] border border-transparent rounded-lg font-semibold text-sm text-white transition-colors duration-200 shadow-md">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                </svg>
                                Buat User
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="bg-white shadow-lg rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600">Daftar User</p>
                        <p class="text-lg font-semibold text-gray-900">Dibuat oleh admin</p>
                    </div>
                    <span class="text-xs px-3 py-1 rounded-full bg-[#e8f1fb] text-[#0057b7] font-semibold border border-[#c7d9f3]">
                        Total: {{ $users->total() }}
                    </span>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead>
                            <tr class="text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                <th class="py-3">Nama</th>
                                <th class="py-3">Email</th>
                                <th class="py-3">Divisi</th>
                                <th class="py-3">Role</th>
                                <th class="py-3">Dibuat</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr class="hover:bg-gray-50">
                                    <td class="py-3 font-semibold text-gray-900">{{ $user->name }}</td>
                                    <td class="py-3 text-gray-700">{{ $user->email }}</td>
                                    <td class="py-3">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ optional($user->division)->is_super_user ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-[#e8f1fb] text-[#0057b7] border border-[#c7d9f3]' }}">
                                            {{ $user->division->name ?? 'Tidak ada' }}
                                        </span>
                                    </td>
                                    <td class="py-3 text-gray-700">
                                        {{ $user->roles->pluck('name')->implode(', ') ?: '-' }}
                                    </td>
                                    <td class="py-3 text-gray-500">{{ $user->created_at?->format('d M Y') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-gray-500">Belum ada user.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($users->hasPages())
                    <div class="px-6 py-4 border-t border-gray-200">
                        {{ $users->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
