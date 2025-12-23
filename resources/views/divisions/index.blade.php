<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Divisi
                </h2>
                <p class="text-sm text-gray-500 mt-1">Kelola daftar divisi yang dapat dipilih saat membuat user.</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 text-green-800 px-4 py-3">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 text-red-800 px-4 py-3">
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-1 bg-white rounded-xl shadow border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Buat Divisi</h3>
                    <form action="{{ route('divisions.store') }}" method="POST" class="space-y-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nama Divisi</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="w-full rounded-lg border-gray-300 focus:border-indigo-600 focus:ring focus:ring-indigo-200 text-sm"
                                   placeholder="Contoh: MIS" required>
                        </div>
                        <button type="submit"
                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-sm rounded-lg shadow">
                            Simpan Divisi
                        </button>
                    </form>
                </div>

                <div class="md:col-span-2 bg-white rounded-xl shadow border border-gray-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Daftar Divisi</h3>
                        <span class="text-sm text-gray-500">Total: {{ $divisions->count() }}</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">ID</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Nama</th>
                                    <th class="px-4 py-2 text-left font-semibold text-gray-700">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($divisions as $division)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-700">{{ $division->id }}</td>
                                        <td class="px-4 py-2 text-gray-900 font-medium">
                                            <form action="{{ route('divisions.update', $division) }}" method="POST" class="flex items-center space-x-2">
                                                @csrf
                                                @method('PUT')
                                                <input type="text" name="name" value="{{ $division->name }}" class="w-full rounded-md border-gray-300 focus:border-indigo-600 focus:ring focus:ring-indigo-200 text-sm">
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm">Update</button>
                                            </form>
                                        </td>
                                        <td class="px-4 py-2">
                                            <form action="{{ route('divisions.destroy', $division) }}" method="POST" onsubmit="return confirm('Hapus divisi {{ $division->name }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-sm">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-gray-500">Belum ada divisi.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
