<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                @if (session('success'))
                    <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                        <span class="font-medium">Sukses!</span> {{ session('success') }}
                    </div>
                @endif
                @if ($errors->any())
                    <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                         <span class="font-medium">Error!</span> {{ $errors->first() }}
                    </div>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div>
                        <h3 class="font-semibold text-lg text-gray-800 border-b pb-2 mb-4">
                            Unggah Laporan
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Gunakan form ini untuk mengimpor data Excel menggunakan format yang sudah terdaftar.
                        </p>
                        {{-- Form ini dari kode asli Anda, dipastikan berfungsi --}}
                        <form action="{{ route('upload.process') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="mapping_id" :value="__('Pilih Format Laporan')" />
                                <select name="mapping_id" id="mapping_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">-- Harap Pilih --</option>
                                    @foreach($mappings as $mapping)
                                        <option value="{{ $mapping->id }}">{{ $mapping->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="data_file" :value="__('Pilih File Excel')" />
                                {{-- [PERBAIKAN] Pastikan nama input adalah 'data_file' --}}
                                <input type="file" name="data_file" id="data_file" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" required>
                            </div>
                            <div>
                                <x-primary-button type="submit">
                                    {{ __('Unggah dan Proses') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                           <h3 class="font-semibold text-lg text-gray-800">
                                Manajemen Format
                            </h3>
                            @can('register format')
                                <a href="{{ route('mapping.register.form') }}" class="text-sm">
                                    <x-secondary-button>
                                        + Buat Format Baru
                                    </x-secondary-button>
                                </a>
                            @endcan
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Lihat daftar format yang sudah terdaftar di sistem.
                        </p>
                        <div class="border rounded-lg max-h-60 overflow-y-auto">
                            <ul class="divide-y divide-gray-200">
                                @forelse ($mappings as $mapping)
                                    <li class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $mapping->name }}</p>
                                        <p class="text-xs text-gray-500">Kolom: {{ $mapping->columns->pluck('database_column')->implode(', ') }}</p>
                                    </li>
                                @empty
                                    <li class="px-4 py-4 text-sm text-gray-500 text-center">
                                        Belum ada format yang terdaftar.
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>