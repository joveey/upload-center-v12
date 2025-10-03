<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0 w-12 h-12 bg-blue-600 rounded-lg flex items-center justify-center shadow-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                        {{ __('Daftar Format') }}
                    </h2>
                    <p class="mt-1 text-sm text-gray-600">
                        Kelola semua format laporan yang terdaftar
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
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            {{-- Diubah: Warna ikon kartu statistik --}}
                            <div class="flex-shrink-0 bg-blue-100 rounded-xl p-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Format</p>
                                <p class="text-3xl font-bold text-gray-900">{{ $totalFormats }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-100 rounded-xl p-3">
                                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Kolom</p>
                                <p class="text-3xl font-bold text-gray-900">{{ $mappings->sum(function($m) { return $m->columns->count(); }) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 rounded-xl p-3">
                                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Baris Data</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($mappings->sum('row_count')) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-orange-100 rounded-xl p-3">
                                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Rata-rata Kolom</p>
                                <p class="text-3xl font-bold text-gray-900">{{ $totalFormats > 0 ? round($mappings->sum(function($m) { return $m->columns->count(); }) / $totalFormats, 1) : 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse($mappings as $mapping)
                    {{-- Diubah: Border hover kartu --}}
                    <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100 hover:shadow-2xl hover:border-blue-300 transition-all duration-300 transform hover:-translate-y-1">
                        {{-- Diubah: Gradient header kartu --}}
                        <div class="bg-gradient-to-r from-blue-500 to-cyan-500 px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-white truncate" title="{{ $mapping->description }}">
                                        {{ $mapping->description ?? $mapping->code }}
                                    </h3>
                                    {{-- Diubah: Warna teks subjudul --}}
                                    <p class="text-sm text-blue-100 mt-1">
                                        {{ $mapping->columns->count() }} kolom • {{ number_format($mapping->row_count) }} baris
                                    </p>
                                </div>
                                <div class="flex-shrink-0 ml-3">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-white/20 text-white backdrop-blur-sm">
                                        {{ $mapping->code }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="p-6">
                            <div class="mb-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
                                <div class="flex items-center text-sm">
                                    {{-- Diubah: Warna ikon info tabel --}}
                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M3 12v3c0 1.657 3.134 3 7 3s7-1.343 7-3v-3c0 1.657-3.134 3-7 3s-7-1.343-7-3z"></path>
                                        <path d="M3 7v3c0 1.657 3.134 3 7 3s7-1.343 7-3V7c0 1.657-3.134 3-7 3S3 8.657 3 7z"></path>
                                        <path d="M17 5c0 1.657-3.134 3-7 3S3 6.657 3 5s3.134-3 7-3 7 1.343 7 3z"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-700">Tabel:</span>
                                    <span class="ml-2 text-gray-600 font-mono text-xs">{{ $mapping->table_name }}</span>
                                </div>
                                <div class="flex items-center text-sm mt-2">
                                    {{-- Diubah: Warna ikon info header row --}}
                                    <svg class="w-4 h-4 mr-2 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd"></path>
                                    </svg>
                                    <span class="font-semibold text-gray-700">Header Row:</span>
                                    <span class="ml-2 text-gray-600">Baris {{ $mapping->header_row }}</span>
                                </div>
                            </div>

                            <div class="mb-4">
                                <p class="text-xs font-semibold text-gray-700 mb-2 uppercase tracking-wide">Kolom Database:</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($mapping->columns->take(6) as $col)
                                        {{-- Diubah: Warna badge kolom --}}
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-blue-100 text-blue-800">
                                            {{ $col->excel_column_index }}: {{ $col->table_column_name }}
                                        </span>
                                    @endforeach
                                    @if($mapping->columns->count() > 6)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-medium bg-gray-200 text-gray-700">
                                            +{{ $mapping->columns->count() - 6 }} lagi
                                        </span>
                                    @endif
                                </div>
                            </div>

                            @php
                                $uniqueKeys = $mapping->columns->where('is_unique_key', true);
                            @endphp
                            @if($uniqueKeys->count() > 0)
                                <div class="mb-4 p-2 bg-amber-50 border border-amber-200 rounded-lg">
                                    <div class="flex items-start">
                                        <svg class="w-4 h-4 text-amber-600 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div class="flex-1">
                                            <p class="text-xs font-semibold text-amber-800">Unique Keys:</p>
                                            <p class="text-xs text-amber-700 mt-1">
                                                {{ $uniqueKeys->pluck('table_column_name')->implode(', ') }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="grid grid-cols-2 gap-3">
                                <a href="{{ route('mapping.view.data', $mapping->id) }}" 
                                   class="inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-blue-700 hover:to-cyan-700 focus:outline-none focus:ring-2 focus:ring-blue-300 transition-all duration-200 shadow-md hover:shadow-lg group">
                                    <svg class="w-4 h-4 mr-2 group-hover:scale-110 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Lihat
                                </a>
                                <a href="{{ route('export.data', $mapping->id) }}" 
                                   class="inline-flex items-center justify-center px-4 py-3 bg-gradient-to-r from-green-600 to-emerald-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-green-700 hover:to-emerald-700 focus:outline-none focus:ring-2 focus:ring-green-300 transition-all duration-200 shadow-md hover:shadow-lg group">
                                    <svg class="w-4 h-4 mr-2 group-hover:-translate-y-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                    </svg>
                                    Export
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full">
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-12 text-center">
                            <svg class="mx-auto h-24 w-24 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <h3 class="text-xl font-bold text-gray-900 mb-2">Belum Ada Format</h3>
                            <p class="text-gray-600 mb-6">Buat format baru untuk memulai mengelola data Excel Anda</p>
                            @can('register format')
                                {{-- Diubah: Tombol pada state kosong --}}
                                <a href="{{ route('mapping.register.form') }}" 
                                   class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-blue-600 to-cyan-600 border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-blue-700 hover:to-cyan-700 focus:outline-none focus:ring-4 focus:ring-blue-300 transition-all duration-300 shadow-lg hover:shadow-xl">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Buat Format Pertama
                                </a>
                            @endcan
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>