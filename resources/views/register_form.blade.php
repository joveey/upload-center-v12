<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center space-x-4">
            <div class="flex-shrink-0 w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center shadow-sm">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </div>
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                    {{ __('Daftarkan Format & Buat Tabel Baru') }}
                </h2>
                <p class="text-sm text-gray-600 mt-1">Buat format baru untuk import data Excel</p>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-lg sm:rounded-lg border border-gray-200">
                <!-- Header Card -->
                <div class="bg-blue-600 px-8 py-6 border-b border-blue-700">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 bg-blue-700 rounded-lg p-2">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold text-white">Konfigurasi Format Baru</h3>
                            <p class="text-blue-100 text-sm">Lengkapi informasi berikut untuk membuat format import</p>
                        </div>
                    </div>
                </div>

                <div class="p-8">
                    <form action="{{ route('mapping.register.process') }}" method="POST" class="space-y-6">
                        @csrf
                        
                        {{-- Error Messages --}}
                        @if ($errors->any())
                            <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-red-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="text-red-800 font-semibold mb-1">Terdapat kesalahan:</h4>
                                        <ul class="list-disc list-inside space-y-1">
                                            @foreach ($errors->all() as $error)
                                                <li class="text-red-700 text-sm">{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        @endif

                        @if(session('error'))
                            <div class="bg-red-50 border-l-4 border-red-500 rounded-lg p-4 shadow-sm">
                                <div class="flex items-start">
                                    <svg class="w-5 h-5 text-red-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                    </svg>
                                    <p class="text-red-700 text-sm font-medium">{{ session('error') }}</p>
                                </div>
                            </div>
                        @endif

                        <!-- Basic Information Section -->
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-lg font-bold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Informasi Dasar
                            </h4>

                            <div class="space-y-4">
                                <div>
                                    <x-input-label for="name" :value="__('Nama/Deskripsi Format')" class="text-gray-700 font-semibold mb-2" />
                                    <x-text-input id="name" class="block w-full px-4 py-3 border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg shadow-sm transition-all duration-200" type="text" name="name" :value="old('name')" required autofocus placeholder="Contoh: Laporan Penjualan Bulanan" />
                                    <p class="mt-1.5 text-xs text-gray-600">Deskripsi yang jelas untuk memudahkan identifikasi format</p>
                                </div>

                                <div>
                                    <x-input-label for="table_name" :value="__('Nama Tabel Baru di Database')" class="text-gray-700 font-semibold mb-2" />
                                    <x-text-input id="table_name" class="block w-full px-4 py-3 border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg shadow-sm font-mono text-sm transition-all duration-200" type="text" name="table_name" :value="old('table_name')" required placeholder="data_penjualan_2024"/>
                                    <p class="mt-1.5 text-xs text-gray-600 flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                        Gunakan huruf kecil, angka, dan underscore (_). Tanpa spasi atau karakter khusus.
                                    </p>
                                </div>

                                <div>
                                    <x-input-label for="header_row" :value="__('Data dimulai dari baris ke-')" class="text-gray-700 font-semibold mb-2" />
                                    <x-text-input id="header_row" class="block w-full px-4 py-3 border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg shadow-sm transition-all duration-200" type="number" name="header_row" :value="old('header_row', 1)" required min="1" />
                                    <p class="mt-1.5 text-xs text-gray-600">Nomor baris tempat header/data dimulai pada file Excel</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Column Mapping Section with Unique Key Checkbox -->
                        <div x-data="{ mappings: [{ excel_column: '', db_column: '', is_unique: false }] }" class="bg-gray-50 rounded-lg p-6 border border-gray-200">
                            <div class="flex items-center justify-between mb-4">
                                <h4 class="text-lg font-bold text-gray-900 flex items-center">
                                    <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                    </svg>
                                    Pemetaan Kolom
                                </h4>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                    <span x-text="mappings.length"></span> kolom
                                </span>
                            </div>
                            <p class="text-sm text-gray-600 mb-4">Tentukan kolom Excel (A, B, C) dan nama kolom yang akan dibuat di tabel database.</p>

                            <div class="space-y-3">
                                <template x-for="(mapping, index) in mappings" :key="index">
                                    <div class="p-4 bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200">
                                        <div class="flex items-start space-x-3">
                                            <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                                                <span x-text="index + 1"></span>
                                            </div>
                                            <div class="flex-1 space-y-3">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kolom Excel</label>
                                                        <input x-model="mapping.excel_column" x-bind:id="'excel_column_' + index" class="block w-full px-3 py-2 border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg shadow-sm uppercase text-sm transition-all duration-200" type="text" x-bind:name="'mappings[' + index + '][excel_column]'" required placeholder="A" maxlength="3" />
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Nama Kolom Database</label>
                                                        <input x-model="mapping.db_column" x-bind:id="'db_column_' + index" class="block w-full px-3 py-2 border-gray-300 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-lg shadow-sm font-mono text-sm transition-all duration-200" type="text" x-bind:name="'mappings[' + index + '][database_column]'" required placeholder="nama_produk"/>
                                                    </div>
                                                </div>
                                                <!-- Checkbox Kunci Unik -->
                                                <div class="flex items-center space-x-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2.5">
                                                    <input 
                                                        type="hidden" 
                                                        x-bind:name="'mappings[' + index + '][is_unique_key]'" 
                                                        value="0"
                                                    >
                                                    <input 
                                                        type="checkbox" 
                                                        x-model="mapping.is_unique" 
                                                        x-bind:id="'is_unique_' + index" 
                                                        x-bind:name="'mappings[' + index + '][is_unique_key]'" 
                                                        value="1" 
                                                        class="rounded border-amber-300 text-amber-600 shadow-sm focus:border-amber-300 focus:ring focus:ring-amber-200 focus:ring-opacity-50"
                                                    >
                                                    <label x-bind:for="'is_unique_' + index" class="text-sm font-semibold text-amber-800 cursor-pointer flex items-center flex-1">
                                                        <svg class="w-4 h-4 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                                                        </svg>
                                                        Jadikan Kunci Unik?
                                                    </label>
                                                    <div class="group relative flex-shrink-0">
                                                        <svg class="w-4 h-4 text-amber-600 cursor-help" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                        </svg>
                                                        <div class="absolute bottom-full right-0 mb-2 hidden group-hover:block w-64 p-3 bg-gray-900 text-white text-xs rounded-lg shadow-lg z-10">
                                                            <p class="font-semibold mb-1">💡 Kunci Unik untuk Upsert</p>
                                                            <p>Kolom ini akan digunakan untuk mengidentifikasi data duplikat saat mode Upsert. Data dengan kunci yang sama akan di-update, bukan duplikat.</p>
                                                            <div class="absolute top-full right-4 -mt-1">
                                                                <div class="border-4 border-transparent border-t-gray-900"></div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span x-show="mapping.is_unique" class="text-xs text-green-600 font-bold">✓ Active</span>
                                                </div>
                                            </div>
                                            <button type="button" @click="mappings.splice(index, 1)" x-show="mappings.length > 1" class="flex-shrink-0 p-2 text-red-500 hover:bg-red-50 rounded-lg transition-colors duration-200 group">
                                                <svg class="w-5 h-5 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="mt-4">
                                <button type="button" @click="mappings.push({ excel_column: '', db_column: '', is_unique: false })" class="inline-flex items-center px-4 py-2.5 bg-blue-600 border border-transparent rounded-lg font-semibold text-sm text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-sm hover:shadow-md group">
                                    <svg class="w-5 h-5 mr-2 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Tambah Kolom
                                </button>
                            </div>

                            <!-- Info Box tentang Kunci Unik -->
                            <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <div class="flex items-start space-x-3">
                                    <svg class="w-5 h-5 text-blue-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <h5 class="text-sm font-semibold text-blue-900 mb-1">Tentang Kunci Unik</h5>
                                        <p class="text-xs text-blue-800">
                                            Kolom yang ditandai sebagai <strong>Kunci Unik</strong> akan digunakan saat mode <strong>Upsert</strong> untuk mencegah duplikasi data. 
                                            Jika data dengan kunci yang sama sudah ada, data tersebut akan di-update. 
                                            Anda bisa menandai lebih dari satu kolom sebagai kunci unik (composite key).
                                        </p>
                                        <p class="text-xs text-blue-700 mt-2">
                                            <strong>Contoh:</strong> Jika Anda menandai kolom <code class="bg-blue-100 px-1 py-0.5 rounded">product_code</code> sebagai kunci unik, 
                                            maka product dengan kode yang sama tidak akan diduplikat.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Section -->
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <a href="{{ route('dashboard') }}" class="inline-flex items-center px-6 py-3 bg-gray-100 border border-gray-300 rounded-lg font-semibold text-sm text-gray-700 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200 group">
                                <svg class="w-5 h-5 mr-2 group-hover:-translate-x-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                                </svg>
                                Kembali
                            </a>

                            <button type="submit" class="inline-flex items-center px-8 py-3 bg-blue-600 border border-transparent rounded-lg font-bold text-sm text-white uppercase tracking-wide hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-md hover:shadow-lg group">
                                <svg class="w-5 h-5 mr-2 group-hover:rotate-12 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Simpan Format & Buat Tabel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Cards -->
            <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 rounded-lg p-4 border border-blue-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-blue-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <h5 class="font-semibold text-gray-800 text-sm">Format Tabel</h5>
                            <p class="text-xs text-gray-600 mt-1">Nama tabel harus unik dan mengikuti konvensi database</p>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-gray-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <div>
                            <h5 class="font-semibold text-gray-800 text-sm">Kolom Excel</h5>
                            <p class="text-xs text-gray-600 mt-1">Gunakan huruf kolom Excel (A, B, C, dst)</p>
                        </div>
                    </div>
                </div>

                <div class="bg-amber-50 rounded-lg p-4 border border-amber-200">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-8 h-8 bg-amber-600 rounded-lg flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                            </svg>
                        </div>
                        <div>
                            <h5 class="font-semibold text-gray-800 text-sm">Kunci Unik</h5>
                            <p class="text-xs text-gray-600 mt-1">Tandai kolom untuk mencegah duplikasi data</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form?.addEventListener('submit', function(e) {
                console.log('🚀 Form submitted!');
                
                const formData = new FormData(this);
                console.log('📦 Form data:');
                for (let [key, value] of formData.entries()) {
                    console.log(`  ${key}: ${value}`);
                }
            });
        });
    </script>
</x-app-layout>