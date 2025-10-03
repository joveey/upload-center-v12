<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
    <div class="flex items-center space-x-4">
        <div class="flex-shrink-0 w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center shadow-sm">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
        </div>
        <div>
            <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600">
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
            <button class="inline-flex items-center px-5 py-2.5 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-lg font-medium text-sm text-white transition-colors duration-200 shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

            {{-- SuperUser Statistics --}}
            @if(auth()->user()->division->is_super_user)
            <div class="mb-6 grid grid-cols-1 lg:grid-cols-3 gap-6">
                {{-- Upload Count per Division --}}
                @if(isset($divisionUploadCounts) && count($divisionUploadCounts) > 0)
                <div class="lg:col-span-1">
                    <div class="bg-white overflow-hidden shadow-xl rounded-2xl border border-gray-100 h-full">
                        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-white/20 backdrop-blur-sm rounded-xl p-2 shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-base font-bold text-white">
                                        Total Upload
                                    </h3>
                                    <p class="text-blue-100 text-xs mt-0.5">
                                        Per Divisi
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <div class="space-y-3">
                                @foreach($divisionUploadCounts as $divCount)
                                <div class="flex items-center justify-between p-3 bg-gradient-to-r from-blue-50 to-cyan-50 rounded-lg border border-blue-100 hover:shadow-md transition-shadow duration-200">
                                    <div class="flex items-center space-x-3">
                                        <div class="flex-shrink-0 w-10 h-10 bg-gradient-to-br from-blue-500 to-cyan-500 rounded-lg flex items-center justify-center shadow-sm">
                                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                            </svg>
                                        </div>
                                        <span class="font-semibold text-gray-800">{{ $divCount['name'] }}</span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold bg-gradient-to-r from-blue-600 to-cyan-600 text-white shadow-sm">
                                            {{ $divCount['count'] }}
                                        </span>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                {{-- Chart --}}
                @if(isset($uploadStats))
                <div class="lg:col-span-2">
                    <div class="bg-white overflow-hidden shadow-xl rounded-2xl border border-gray-100 h-full">
                        <div class="bg-gradient-to-r from-indigo-500 via-purple-500 to-pink-500 px-6 py-4">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 bg-white/20 backdrop-blur-sm rounded-xl p-2 shadow-lg">
                                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-base font-bold text-white">
                                        Tren Upload
                                    </h3>
                                    <p class="text-indigo-100 text-xs mt-0.5">
                                        4 minggu terakhir
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="p-6">
                            <canvas id="uploadChart" height="80"></canvas>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2">
        <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-gray-200 hover:shadow-xl transition-shadow duration-200">
            <div class="bg-blue-600 px-6 py-4 border-b border-blue-700">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-700 rounded-lg p-2">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-base font-semibold text-white">
                            Unggah Laporan
                        </h3>
                        <p class="text-blue-100 text-sm mt-0.5">
                            Import data Excel dengan format yang sudah terdaftar
                        </p>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <form id="uploadForm" method="POST" enctype="multipart/form-data" class="space-y-5">
                    @csrf
                    <div>
                        <label for="mapping_id" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Format Laporan
                        </label>
                        <select name="mapping_id" id="mapping_id" class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition duration-150 py-2.5 px-3" required>
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
                            <p class="mt-3 text-sm text-amber-700 flex items-center bg-amber-50 p-3 rounded-lg border border-amber-200">
                                <svg class="w-5 h-5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                                Belum ada format. Silakan buat format baru terlebih dahulu.
                            </p>
                        @endif
                    </div>

                    
                    <div>
                        <label for="data_file" class="block text-sm font-semibold text-gray-700 mb-2 flex items-center">
                            <svg class="w-4 h-4 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            File Excel
                        </label>
                        <div id="drop-zone" class="mt-1 flex justify-center px-6 pt-6 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-blue-400 transition-colors duration-200 bg-gray-50">
                            <div class="space-y-2 text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                    <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <div class="flex text-sm text-gray-600">
                                    <label for="data_file" class="relative cursor-pointer bg-white rounded-md font-medium text-blue-600 hover:text-blue-700 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500 px-3 py-1">
                                        <span>Upload file</span>
                                        <input id="data_file" name="data_file" type="file" accept=".xlsx,.xls" class="sr-only" required>
                                    </label>
                                    <p class="pl-1">atau drag & drop</p>
                                </div>
                                <p class="text-xs text-gray-500">XLSX, XLS hingga 10MB</p>
                            </div>
                        </div>
                        <p id="file-name" class="mt-3 text-sm text-gray-700 font-medium hidden bg-blue-50 p-2 rounded-lg border border-blue-200"></p>
                    </div>

                    <div class="pt-2">
                        <button type="button" id="previewButton" class="w-full inline-flex justify-center items-center px-5 py-3 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-lg font-medium text-sm text-white transition-colors duration-200 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <div class="bg-white overflow-hidden shadow-xl rounded-lg border border-gray-200">
    <div class="bg-blue-600 px-6 py-4 border-b border-blue-700">
        <div class="flex items-center justify-between">
            <div class="flex items-center">
                <div class="flex-shrink-0 bg-blue-700 rounded-lg p-2">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-base font-semibold text-white">Format</h3>
                    <p class="text-sm text-blue-100">{{ $mappings->count() }} tersedia</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="p-4 max-h-[600px] overflow-y-auto custom-scrollbar">
        @forelse ($mappings as $mapping)
            <div class="mb-4 p-4 bg-white rounded-lg border border-gray-200 hover:border-blue-400 hover:shadow-md transition-all duration-200">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex-1">
                        <h4 class="font-semibold text-gray-900 mb-2">
                            {{ $mapping->description ?? $mapping->code }}
                        </h4>
                        <div class="space-y-2">
                            <p class="text-xs text-gray-600 flex items-center">
                                <svg class="w-3 h-3 mr-1.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                </svg>
                                <span class="font-medium">Code:</span> <span class="ml-1 font-mono text-gray-700">{{ $mapping->code }}</span>
                            </p>
                            <p class="text-xs text-gray-600 flex items-center">
                                <svg class="w-3 h-3 mr-1.5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"></path>
                                    <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"></path>
                                    <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"></path>
                                </svg>
                                <span class="font-medium">Tabel:</span> <span class="ml-1 font-mono text-gray-700">{{ $mapping->table_name }}</span>
                            </p>
                            <div class="flex flex-wrap gap-1 mt-2">
                                @foreach($mapping->columns->take(3) as $col)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200">
                                        {{ $col->table_column_name }}
                                    </span>
                                @endforeach
                                @if($mapping->columns->count() > 3)
                                    <span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-gray-100 text-gray-600 border border-gray-200">
                                        +{{ $mapping->columns->count() - 3 }} lagi
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mt-3 pt-3 border-t border-gray-200 grid grid-cols-2 gap-2">
                    <a href="{{ route('mapping.view.data', $mapping->id) }}" 
                       class="inline-flex items-center justify-center px-3 py-2 bg-blue-600 hover:bg-blue-700 border border-transparent rounded-lg font-medium text-xs text-white transition-colors duration-200">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        Lihat Data
                    </a>
                    <a href="{{ route('export.data', $mapping->id) }}" 
                       class="inline-flex items-center justify-center px-3 py-2 bg-green-600 hover:bg-green-700 border border-transparent rounded-lg font-medium text-xs text-white transition-colors duration-200">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                        </svg>
                        Download
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
            const dropZone = document.getElementById('drop-zone');
            
            // Handle file input change
            fileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    fileName.textContent = '📎 ' + this.files[0].name;
                    fileName.classList.remove('hidden');
                } else {
                    fileName.classList.add('hidden');
                }
            });

            // Drag and Drop functionality
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, preventDefaults, false);
            });

            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }

            // Highlight drop zone when dragging over it
            ['dragenter', 'dragover'].forEach(eventName => {
                dropZone.addEventListener(eventName, highlight, false);
            });

            ['dragleave', 'drop'].forEach(eventName => {
                dropZone.addEventListener(eventName, unhighlight, false);
            });

            function highlight(e) {
                dropZone.classList.add('border-blue-500', 'bg-blue-50', 'border-4');
                dropZone.classList.remove('border-gray-300', 'bg-gray-50');
            }

            function unhighlight(e) {
                dropZone.classList.remove('border-blue-500', 'bg-blue-50', 'border-4');
                dropZone.classList.add('border-gray-300', 'bg-gray-50');
            }

            // Handle dropped files
            dropZone.addEventListener('drop', handleDrop, false);

            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;

                if (files.length > 0) {
                    const file = files[0];
                    
                    // Check file type
                    const validTypes = [
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', // .xlsx
                        'application/vnd.ms-excel' // .xls
                    ];
                    
                    if (!validTypes.includes(file.type) && !file.name.match(/\.(xlsx|xls)$/i)) {
                        alert('❌ File harus berformat Excel (.xlsx atau .xls)');
                        return;
                    }

                    // Check file size (10MB)
                    if (file.size > 10 * 1024 * 1024) {
                        alert('❌ Ukuran file maksimal 10MB');
                        return;
                    }

                    // Set file to input
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add(file);
                    fileInput.files = dataTransfer.files;

                    // Show file name
                    fileName.textContent = '📎 ' + file.name;
                    fileName.classList.remove('hidden');

                    // Visual feedback
                    dropZone.classList.add('border-green-500', 'bg-green-50');
                    setTimeout(() => {
                        dropZone.classList.remove('border-green-500', 'bg-green-50');
                        dropZone.classList.add('border-gray-300', 'bg-gray-50');
                    }, 1000);
                }
            }

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