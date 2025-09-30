<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftarkan Format & Buat Tabel Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <form action="{{ route('mapping.register.process') }}" method="POST">
                        @csrf
                        
                        {{-- Menampilkan error validasi jika ada --}}
                        @if ($errors->any())
                            <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Nama/Deskripsi Format')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="table_name" :value="__('Nama Tabel Baru di Database')" />
                            <x-text-input id="table_name" class="block mt-1 w-full font-mono" type="text" name="table_name" :value="old('table_name')" required placeholder="contoh: data_penjualan_baru"/>
                            <p class="mt-1 text-sm text-gray-600">Gunakan huruf kecil, angka, dan underscore (_). Tanpa spasi.</p>
                        </div>

                        <div class="mb-8">
                            <x-input-label for="header_row" :value="__('Data dimulai dari baris ke-')" />
                            <x-text-input id="header_row" class="block mt-1 w-full" type="number" name="header_row" :value="old('header_row', 1)" required min="1" />
                        </div>
                        
                        <div x-data="{ mappings: [{ excel_column: '', db_column: '' }] }">
                            <h3 class="font-semibold text-lg">Pemetaan Kolom</h3>
                            <p class="text-sm text-gray-600 mb-4">Tentukan kolom Excel (A, B, C) dan nama kolom yang akan dibuat di tabel baru.</p>

                            <template x-for="(mapping, index) in mappings" :key="index">
                               <div class="flex items-center space-x-4 mb-4 p-4 border rounded-md bg-gray-50">
                                    <div class="flex-1">
                                        <x-input-label x-bind:for="'excel_column_' + index" :value="__('Kolom di Excel (A, B, ...)')" />
                                        <x-text-input x-model="mapping.excel_column" x-bind:id="'excel_column_' + index" class="block mt-1 w-full uppercase" type="text" x-bind:name="'mappings[' + index + '][excel_column]'" required />
                                    </div>
                                    <div class="flex-1">
                                        <x-input-label x-bind:for="'db_column_' + index" :value="__('Nama Kolom di Database')" />
                                        <x-text-input x-model="mapping.db_column" x-bind:id="'db_column_' + index" class="block mt-1 w-full font-mono" type="text" x-bind:name="'mappings[' + index + '][database_column]'" required placeholder="contoh: nama_barang"/>
                                    </div>
                                    <div class="flex-shrink-0">
                                        <button type="button" @click="mappings.splice(index, 1)" x-show="mappings.length > 1" class="p-2 mt-5 text-red-500 hover:text-red-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div class="mt-4">
                                <x-secondary-button type="button" @click="mappings.push({ excel_column: '', db_column: '' })">
                                    + Tambah Kolom
                                </x-secondary-button>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-8 border-t pt-6">
                            <x-primary-button class="ml-4">
                                Simpan Format & Buat Tabel
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>