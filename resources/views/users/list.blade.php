<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">Daftar Pengguna</h2>
                <p class="text-sm text-gray-600 mt-1">Kelola user dengan pencarian & pagination.</p>
            </div>
            @can('manage users')
                <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-4 py-2 bg-[#0057b7] hover:bg-[#004a99] text-white text-sm font-semibold rounded-lg shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                    </svg>
                    Buat User
                </a>
            @endcan
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
            @if (session('error'))
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white rounded-2xl shadow border border-gray-200">
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between bg-gray-50">
                    <div>
                        <p class="text-sm text-gray-600">Daftar User</p>
                        <p class="text-lg font-semibold text-gray-900">Total: {{ $users->total() }}</p>
                    </div>
                    <form method="GET" action="{{ route('admin.users.list') }}" class="relative">
                        <input type="text" name="q" value="{{ $search }}" placeholder="Cari nama/email..."
                               class="pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm w-64 md:w-72" />
                        <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"></path>
                        </svg>
                    </form>
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
                                <th class="py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($users as $user)
                                <tr class="hover:bg-gray-50 align-top">
                                    <td class="py-3 font-semibold text-gray-900">
                                        {{ $user->name }}
                                        <p class="text-xs text-gray-500">#{{ $user->id }}</p>
                                    </td>
                                    <td class="py-3 text-gray-700">{{ $user->email }}</td>
                                    <td class="py-3">
                                        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-2">
                                            @csrf
                                            @method('PUT')
                                            <input type="hidden" name="name" value="{{ $user->name }}">
                                            <input type="hidden" name="email" value="{{ $user->email }}">
                                            <select name="division_id" class="w-full rounded-md border-gray-300 text-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30">
                                                @foreach ($divisions as $division)
                                                    <option value="{{ $division->id }}" @selected($user->division_id == $division->id)>{{ $division->name }}</option>
                                                @endforeach
                                            </select>
                                    </td>
                                    <td class="py-3">
                                            <select name="role" class="w-full rounded-md border-gray-300 text-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30 mb-2">
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->name }}" @selected($user->roles->pluck('name')->first() === $role->name)>{{ $role->name }}</option>
                                                @endforeach
                                            </select>
                                            <div class="flex items-center space-x-2">
                                                <input type="password" name="password" placeholder="Password baru (opsional)" class="w-1/2 rounded-md border-gray-300 text-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30">
                                                <input type="password" name="password_confirmation" placeholder="Konfirmasi" class="w-1/2 rounded-md border-gray-300 text-sm focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30">
                                            </div>
                                    </td>
                                    <td class="py-3 text-gray-500">{{ $user->created_at?->format('d M Y') }}</td>
                                    <td class="py-3 space-y-2">
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm">Update</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Hapus user {{ $user->name }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm">Hapus</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4 text-center text-gray-500">Belum ada user.</td>
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
