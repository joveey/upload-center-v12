<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0 w-14 h-14 bg-gradient-to-br from-indigo-500 via-purple-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg transform hover:rotate-6 transition-transform duration-300">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-3xl text-gray-800 leading-tight">
                        {{ __('Dashboard') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600 font-medium">
                        @if(auth()->user()->division->is_super_user)
                            Admin Panel - Kelola semua data
                        @else
                            Kelola dan unggah data Excel dengan mudah
                        @endif
                    </p>
                </div>
            </div>
            @can('register format')
                <a href="{{ route('mapping.register.form') }}">
                    <button class="group inline-flex items-center px-6 py-3 bg-gradient-to-r from-indigo-600 to-purple-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-indigo-700 hover:to-purple-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all duration-300 shadow-lg hover:shadow-xl hover:-translate-y-0.5 transform">
                        <svg class="w-5 h-5 mr-2 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Buat Format Baru
                    </button>
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6 space-y-3">
                @if (session('success'))
                    <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-xl shadow-md p-5 flex items-start animate-slide-down" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-500 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold text-green-800 text-lg">Berhasil!</span>
                            <p class="text-green-700 text-sm mt-1">{{ session('success') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="ml-4 text-green-500 hover:text-green-700 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                @endif
                @if (session('error'))
                    <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 rounded-xl shadow-md p-5 flex items-start animate-slide-down" role="alert">
                        <div class="flex-shrink-0 w-10 h-10 bg-red-500 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="flex-1">
                            <span class="font-bold text-red-800 text-lg">Error!</span>
                            <p class="text-red-700 text-sm mt-1">{{ session('error') }}</p>
                        </div>
                        <button onclick="this.parentElement.remove()" class="ml-4 text-red-500 hover:text-red-700 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                    </div>
                @endif
            </div>

            {{-- SuperUser Chart --}}
            @if(auth()->user()->division->is_super_user && isset($uploadStats))
            <div class="mb-6">
                <div class="bg-white overflow-hidden shadow-2xl rounded-2xl border border-gray-100">
                    <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-8 py-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-white/20 backdrop-blur-sm rounded-xl p-3 shadow-lg">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-xl font-bold text-white">
                                    Statistik Upload
                                </h3>
                                <p class="text-indigo-100 text-sm mt-1">
                                    Data upload 4 minggu terakhir
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-8">
                        <canvas id="uploadChart" height="80"></canvas>
                    </div>
                </div>
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-2xl rounded-2xl border border-gray-100 hover:shadow-3xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-8 py-6">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-white/20 backdrop-blur-sm rounded-xl p-3 shadow-lg">
                                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                                    </svg>
                                </div>
                                <div class="ml-4">
                                    <h3 class="text-xl font-bold text-white">
                                        Unggah Laporan
                                    </h3>
                                    <p class="text-indigo-100 text-sm mt-1">
                                        Import data Excel dengan format yang sudah terdaftar
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-8">
                            <form id="uploadForm" method="POST" enctype="multipart/form-data" class="space-y-6">
                                @csrf
                                <div>
                                    <label for="mapping_id" class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Format Laporan
                                    </label>
                                    <select name="mapping_id" id="mapping_id" class="block w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 transition duration-200 py-3 px-4" required>
                                        <option value="">Pilih format laporan...</option>
                                        @forelse($mappings as $mapping)
                                            <option value="{{ $mapping->id }}">
                                                {{ $mapping->description ?? $mapping->code }}
                                            </option>
                                        @empty
                                            <option value="" disabled>Belum ada format terdaftar</option>
                                        @endforelse
                                    </select>
                                    @if($mappings->isEmpty())
                                        <p class="mt-3 text-sm text-amber-600 flex items-center bg-amber-50 p-3 rounded-lg border border-amber-200">
                                            <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                            </svg>
                                            Belum ada format. Silakan buat format baru terlebih dahulu.
                                        </p>
                                    @endif
                                </div>

                                <div>
                                    <label for="data_file" class="block text-sm font-bold text-gray-700 mb-2 flex items-center">
                                        <svg class="w-4 h-4 mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        File Excel
                                    </label>
                                    <div class="mt-1 flex justify-center px-6 pt-8 pb-8 border-2 border-gray-300 border-dashed rounded-xl hover:border-indigo-400 transition-all duration-200 bg-gradient-to-br from-gray-50 to-indigo-50 group">
                                        <div class="space-y-2 text-center">
                                            <svg class="mx-auto h-16 w-16 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                                <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            </svg>
                                            <div class="flex text-sm text-gray-600">
                                                <label for="data_file" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500 px-3 py-1">
                                                    <span>Upload file</span>
                                                    <input id="data_file" name="data_file" type="file" accept=".xlsx,.xls" class="sr-only" required>
                                                </label>
                                                <p class="pl-1">atau drag & drop</p>
                                            </div>
                                            <p class="text-xs text-gray-500">XLSX, XLS hingga 10MB</p>
                                        </div>
                                    </div>
                                    <p id="file-name" class="mt-3 text-sm text-gray-700 font-medium hidden bg-indigo-50 p-2 rounded-lg border border-indigo-200"></p>
                                </div>

                                <div class="pt-2">
                                    <button type="button" id="previewButton" class="w-full inline-flex justify-center items-center px-6 py-4 bg-gradient-to-r from-indigo-600 via-purple-600 to-pink-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-indigo-700 hover:via-purple-700 hover:to-pink-700 focus:outline-none focus:ring-4 focus:ring-indigo-300 transition-all duration-300 shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed group">
                                        <svg class="w-5 h-5 mr-2 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Preview & Konfigurasi
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-2xl rounded-2xl border border-gray-100 hover:shadow-3xl transition-all duration-300 transform hover:-translate-y-1">
                        <div class="bg-gradient-to-r from-purple-500 via-pink-500 to-rose-500 px-6 py-5">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 bg-white/20 backdrop-blur-sm rounded-xl p-3 shadow-lg">
                                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-lg font-bold text-white">Format</h3>
                                        <p class="text-sm text-purple-100">{{ $mappings->count() }} tersedia</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-4 max-h-96 overflow-y-auto custom-scrollbar">
                            @forelse ($mappings as $mapping)
                                <div class="mb-3 p-4 bg-gradient-to-br from-gray-50 to-purple-50 rounded-xl border border-purple-200 hover:border-purple-400 hover:shadow-md transition-all duration-200 group">
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1">
                                            <h4 class="font-bold text-gray-900 mb-2 group-hover:text-purple-600 transition-colors">
                                                {{ $mapping->description ?? $mapping->code }}
                                            </h4>
                                            <div class="space-y-2">
                                                <p class="text-xs text-gray-600 flex items-center">
                                                    <svg class="w-3 h-3 mr-1.5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <span class="font-semibold">Code:</span> <span class="ml-1 font-mono">{{ $mapping->code }}</span>
                                                </p>
                                                <p class="text-xs text-gray-600 flex items-center">
                                                    <svg class="w-3 h-3 mr-1.5 text-purple-500" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"></path>
                                                        <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"></path>
                                                        <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"></path>
                                                    </svg>
                                                    <span class="font-semibold">Tabel:</span> <span class="ml-1 font-mono">{{ $mapping->table_name }}</span>
                                                </p>
                                                <div class="flex flex-wrap gap-1 mt-2">
                                                    @foreach($mapping->columns->take(3) as $col)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-purple-100 text-purple-800">
                                                            {{ $col->table_column_name }}
                                                        </span>
                                                    @endforeach
                                                    @if($mapping->columns->count() > 3)
                                                        <span class="inline-flex items-center px-2 py-1 rounded-lg text-xs font-medium bg-gray-200 text-gray-700">
                                                            +{{ $mapping->columns->count() - 3 }} lagi
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    {{-- Download Button --}}
                                    <div class="mt-3 pt-3 border-t border-purple-200">
                                        <a href="{{ route('export.data', $mapping->id) }}" 
                                           class="inline-flex items-center justify-center w-full px-4 py-2 bg-gradient-to-r from-green-600 to-emerald-600 border border-transparent rounded-lg font-bold text-xs text-white uppercase tracking-wide hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-300 transition-all duration-200 shadow-sm hover:shadow-md group">
                                            <svg class="w-4 h-4 mr-2 group-hover:-translate-y-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                            Download Excel
                                        </a>
                                    </div>
                                </div>
                            @empty
                                <div class="text-center py-12">
                                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="mt-3 text-sm text-gray-500 font-medium">Belum ada format</p>
                                    <p class="mt-1 text-xs text-gray-400">Buat format baru untuk memulai</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Preview --}}
    <div id="previewModal" class="hidden fixed inset-0 bg-gray-900 bg-opacity-75 overflow-y-auto h-full w-full z-50 backdrop-blur-sm">
        <div class="relative top-10 mx-auto p-6 border w-11/12 max-w-7xl shadow-2xl rounded-3xl bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-6 pb-5 border-b-2 border-gray-200">
                    <div class="flex items-center space-x-4">
                        <div class="w-12 h-12 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-xl flex items-center justify-center shadow-lg">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-2xl font-bold text-gray-900">Preview & Konfigurasi</h3>
                            <p class="text-sm text-gray-600 mt-1">Tinjau dan sesuaikan mapping sebelum import</p>
                        </div>
                    </div>
                    <button id="closeModalX" class="text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded-xl p-3 transition-all duration-200 group">
                        <svg class="w-6 h-6 group-hover:rotate-90 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="previewContent" class="mb-6">
                    <div class="text-center py-16">
                        <svg class="animate-spin h-16 w-16 mx-auto text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-4 text-gray-600 font-medium">Memuat preview...</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 border-t-2 border-gray-200 pt-5">
                    <button type="button" id="closeModal" class="inline-flex items-center px-6 py-3 bg-gray-100 border border-gray-300 rounded-xl font-bold text-sm text-gray-700 uppercase tracking-wide hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-all duration-200">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Batal
                    </button>
                    <button type="button" id="confirmUpload" class="inline-flex items-center px-8 py-3 bg-gradient-to-r from-green-600 to-emerald-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-4 focus:ring-green-300 transition-all duration-300 shadow-lg hover:shadow-xl group">
                        <svg class="w-5 h-5 mr-2 group-hover:-translate-y-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    {{-- Chart.js untuk SuperUser --}}
    @if(auth()->user()->division->is_super_user && isset($uploadStats))
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const ctx = document.getElementById('uploadChart');
        const uploadData = @json($uploadStats);
        
        const colors = [
            'rgb(99, 102, 241)',   // indigo
            'rgb(168, 85, 247)',   // purple
            'rgb(236, 72, 153)',   // pink
            'rgb(14, 165, 233)',   // sky
            'rgb(34, 197, 94)',    // green
            'rgb(251, 146, 60)',   // orange
        ];

        const datasets = uploadData.datasets.map((dataset, index) => ({
            label: dataset.label,
            data: dataset.data,
            borderColor: colors[index % colors.length],
            backgroundColor: colors[index % colors.length] + '20',
            borderWidth: 3,
            tension: 0.4,
            fill: true
        }));

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: uploadData.labels,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleFont: {
                            size: 14,
                            weight: 'bold'
                        },
                        bodyFont: {
                            size: 13
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    </script>
    @endif

    <script>
        let previewData = null;

        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('data_file');
            const fileName = document.getElementById('file-name');
            
            fileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    fileName.textContent = '📎 ' + this.files[0].name;
                    fileName.classList.remove('hidden');
                } else {
                    fileName.classList.add('hidden');
                }
            });

            const previewButton = document.getElementById('previewButton');
            const confirmUpload = document.getElementById('confirmUpload');
            const closeModal = document.getElementById('closeModal');
            const closeModalX = document.getElementById('closeModalX');
            const modal = document.getElementById('previewModal');
            const form = document.getElementById('uploadForm');

            previewButton.addEventListener('click', function() {
                const mappingId = document.getElementById('mapping_id').value;
                const fileInput = document.getElementById('data_file');
                
                if (!mappingId) {
                    alert('Pilih format terlebih dahulu');
                    return;
                }
                
                if (!fileInput.files[0]) {
                    alert('Pilih file terlebih dahulu');
                    return;
                }

                modal.classList.remove('hidden');

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('mapping_id', mappingId);
                formData.append('data_file', fileInput.files[0]);

                fetch('{{ route("upload.preview") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewData = data;
                        document.getElementById('previewContent').innerHTML = data.html;
                        initializeCheckboxes();
                    } else {
                        alert(data.message || 'Error loading preview');
                        modal.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat preview');
                    modal.classList.add('hidden');
                });
            });

            function initializeCheckboxes() {
                const selectAll = document.getElementById('selectAllColumns');
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.column-checkbox');
                        checkboxes.forEach(cb => cb.checked = this.checked);
                    });
                }
            }
            
            // ===== START: INTEGRATED CODE =====
            confirmUpload.addEventListener('click', function() {
                const selectedColumns = {};
                const checkboxes = document.querySelectorAll('.column-checkbox:checked');
                
                if (checkboxes.length === 0) {
                    alert('Pilih minimal satu kolom untuk diimport');
                    return;
                }

                checkboxes.forEach(cb => {
                    const excelCol = cb.dataset.excelCol;
                    const mappingSelect = document.getElementById('mapping_' + excelCol);
                    if (mappingSelect) {
                        selectedColumns[excelCol] = mappingSelect.value;
                    }
                });

                // Get upload mode
                const uploadMode = document.querySelector('input[name="upload_mode"]:checked').value;

                const formData = new FormData(form);
                formData.append('selected_columns', JSON.stringify(selectedColumns));
                formData.append('upload_mode', uploadMode);
                
                confirmUpload.disabled = true;
                confirmUpload.innerHTML = '<svg class="animate-spin h-5 w-5 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Uploading...';

                fetch('{{ route("upload.process") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error uploading data');
                        confirmUpload.disabled = false;
                        confirmUpload.innerHTML = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>Upload Data';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat upload');
                    confirmUpload.disabled = false;
                    confirmUpload.innerHTML = '<svg class="w-5 h-5 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>Upload Data';
                });
            });
            // ===== END: INTEGRATED CODE =====

            closeModal.addEventListener('click', () => modal.classList.add('hidden'));
            closeModalX.addEventListener('click', () => modal.classList.add('hidden'));
        });
    </script>
    
    <style>
        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #a855f7;
            border-radius: 10px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #9333ea;
        }
        @keyframes slide-down {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-slide-down {
            animation: slide-down 0.3s ease-out;
        }
    </style>
    @endpush
</x-app-layout>