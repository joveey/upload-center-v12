<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <div class="flex-shrink-0 w-12 h-12 bg-[#0057b7] rounded-lg flex items-center justify-center shadow-sm">
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
            <div class="flex items-center space-x-3">
                <form method="GET" action="{{ route('formats.index') }}" class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search ?? '' }}"
                        placeholder="Cari format, kode, atau tabel..."
                        class="pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm w-64 md:w-72"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"></path>
                    </svg>
                </form>
                @can('create format')
                    <a href="{{ route('mapping.register.form') }}">
                        <button class="inline-flex items-center px-5 py-2.5 bg-[#0057b7] hover:bg-[#004a99] border border-transparent rounded-lg font-medium text-sm text-white transition-colors duration-200 shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Buat Format Baru
                        </button>
                    </a>
                @endcan
                @can('manage users')
                    <a href="{{ route('divisions.index') }}">
                        <button class="inline-flex items-center px-4 py-2.5 bg-white border border-gray-200 hover:border-gray-300 rounded-lg font-medium text-sm text-gray-800 transition-colors duration-200 shadow-sm">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7h18M3 12h18M3 17h18"></path>
                            </svg>
                            Kelola Divisi
                        </button>
                    </a>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            
            @if (session('error'))
                <div class="mb-4 rounded-xl border border-red-200 bg-red-50 text-red-700 px-4 py-3 flex items-start space-x-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div class="text-sm font-medium">{{ session('error') }}</div>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 rounded-xl border border-green-200 bg-green-50 text-green-700 px-4 py-3 flex items-start space-x-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    <div class="text-sm font-medium">{{ session('success') }}</div>
                </div>
            @endif

            @if (!empty($search ?? ''))
                <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 text-blue-700 px-4 py-3 flex items-start space-x-3">
                    <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"/>
                    </svg>
                    <div class="text-sm">
                        <span class="font-semibold">Filter:</span> "{{ $search }}"
                        <a href="{{ route('formats.index') }}" class="ml-2 text-[#0057b7] font-semibold hover:underline">Reset</a>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            {{-- Diubah: Warna ikon kartu statistik --}}
                            <div class="flex-shrink-0 bg-[#e0ebf9] rounded-xl p-3">
                                <svg class="w-8 h-8 text-[#0057b7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Format</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($totalFormats) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-[#e0ebf9] rounded-xl p-3">
                                <svg class="w-8 h-8 text-[#0057b7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Kolom</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($totalColumns ?? 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-[#e0ebf9] rounded-xl p-3">
                                <svg class="w-8 h-8 text-[#0057b7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Total Baris Data</p>
                                <p class="text-3xl font-bold text-gray-900">{{ number_format($totalRows ?? 0) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-2xl border border-gray-100">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-[#e0ebf9] rounded-xl p-3">
                                <svg class="w-8 h-8 text-[#0057b7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4 flex-1">
                                <p class="text-sm font-medium text-gray-600">Rata-rata Kolom</p>
                                <p class="text-3xl font-bold text-gray-900">{{ $avgColumns ?? 0 }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-gray-200 shadow-md rounded-xl overflow-hidden">
                <div class="bg-gradient-to-r from-[#0057b7] to-[#0077d9] text-white px-6 py-4 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold">Daftar Format</p>
                        <p class="text-xs text-white/80">Tabel daftar format (tabel ter-register).</p>
                    </div>
                    <span class="text-xs text-white/80">Total: {{ $mappings instanceof \Illuminate\Pagination\LengthAwarePaginator ? $mappings->total() : $mappings->count() }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-700 font-semibold border-b border-gray-200">
                            <tr>
                                <th class="px-6 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Format & Info</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Kode</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Tabel</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Kolom</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Baris</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Header</th>
                                <th class="px-4 py-3 text-left uppercase tracking-wide text-xs text-gray-500">Divisi</th>
                                <th class="px-6 py-3 text-right uppercase tracking-wide text-xs text-gray-500">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100 text-gray-800">
                            @forelse($mappings as $mapping)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3">
                                        <div class="font-semibold text-gray-900">{{ $mapping->description ?? $mapping->code }}</div>
                                        <div class="text-xs text-gray-500 mt-1">Dibuat oleh: {{ $mapping->is_legacy_source ? 'Legacy' : (optional($mapping->division)->name ?? 'Legacy') }}</div>
                                    </td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $mapping->code }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $mapping->table_name }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 text-xs">
                                            {{ $mapping->columns->count() }} kolom
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-100 text-xs">
                                            {{ number_format($mapping->row_count) }} baris
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 border border-gray-200 text-xs">
                                            Baris {{ $mapping->header_row }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full {{ $mapping->is_legacy_source ? 'bg-yellow-50 text-yellow-800 border border-yellow-100' : 'bg-sky-50 text-sky-800 border border-sky-100' }} text-xs">
                                            {{ $mapping->is_legacy_source ? 'Legacy' : (optional($mapping->division)->name ?? '-') }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-3">
                                        <div class="flex items-center justify-end gap-2 flex-wrap">
                                            @can('update format')
                                                <a href="{{ route('mapping.edit', $mapping->id) }}"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-800 hover:bg-gray-50">
                                                    Edit
                                                </a>
                                            @endcan
                                            @can('view data')
                                                <a href="{{ route('mapping.view.data', $mapping->id) }}"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-[#0057b7] text-white text-xs font-semibold hover:bg-[#004a99]">
                                                    Lihat
                                                </a>
                                            @endcan
                                            @can('download template')
                                                <a href="{{ route('export.template', $mapping->id) }}"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-xs font-semibold text-gray-800 hover:bg-gray-50">
                                                    Template
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center text-gray-600">
                                        <div class="flex flex-col items-center space-y-3">
                                            <svg class="h-14 w-14 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                            </svg>
                                            <div>
                                                <p class="text-base font-semibold text-gray-900">Belum Ada Format</p>
                                                <p class="text-sm text-gray-500">Buat format baru untuk memulai mengelola data Excel Anda.</p>
                                            </div>
                                            @can('create format')
                                                <a href="{{ route('mapping.register.form') }}" 
                                                   class="inline-flex items-center px-5 py-2.5 bg-gradient-to-r from-[#0057b7] to-[#00a1e4] border border-transparent rounded-xl font-bold text-sm text-white uppercase tracking-wide hover:from-[#003b7a] hover:to-[#0091cf] focus:outline-none focus:ring-4 focus:ring-[#0057b7]/40 transition-all duration-300 shadow-lg hover:shadow-xl">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                    </svg>
                                                    Buat Format Pertama
                                                </a>
                                            @endcan
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($mappings instanceof \Illuminate\Pagination\LengthAwarePaginator)
                <div class="mt-8">
                    {{ $mappings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>

<!-- Export Modal -->
@php
    $exportPeriods = collect(range(0, 11))->map(function ($i) {
        $dt = \Carbon\Carbon::now()->subMonths($i)->startOfMonth();
        return [
            'value' => $dt->toDateString(),
            'label' => $dt->translatedFormat('F Y'),
        ];
    });
@endphp
<div id="exportModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/50"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-xl font-bold text-gray-900" id="exportModalTitle">Export Data</h3>
                <p class="text-sm text-gray-600 mt-1">Pilih periode (tanggal selalu 1).</p>
            </div>
            <button type="button" id="btn-export-close" class="text-gray-500 hover:text-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="exportForm" method="GET">
            <div class="mb-4">
                <label class="block text-sm font-semibold text-gray-800 mb-1">Periode</label>
                <select name="period_date" class="w-full rounded-lg border-gray-300 focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30 shadow-sm">
                    @foreach($exportPeriods as $opt)
                        <option value="{{ $opt['value'] }}">{{ $opt['label'] }} ({{ $opt['value'] }})</option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-500 mt-1">Opsi sudah otomatis tanggal 1 setiap bulan.</p>
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" id="btn-export-cancel" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" class="px-4 py-2 text-sm font-semibold text-white bg-gradient-to-r from-[#0057b7] to-[#00a1e4] hover:from-[#004a99] hover:to-[#0091cf] rounded-lg">Download</button>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.btn-delete-format').forEach((btn) => {
            btn.addEventListener('click', () => {
                const name = btn.dataset.name || 'format';
                const firstConfirm = confirm(`Hapus format "${name}" beserta tabel datanya?`);
                if (!firstConfirm) {
                    return;
                }

                const secondConfirm = prompt('Ketik KONFIRMASI untuk konfirmasi kedua:');
                if (!secondConfirm || secondConfirm.trim().toUpperCase() !== 'KONFIRMASI') {
                    alert('Penghapusan dibatalkan karena konfirmasi kedua tidak sesuai.');
                    return;
                }

                btn.closest('form').submit();
            });
        });

        document.querySelectorAll('.btn-clear-data').forEach((btn) => {
            btn.addEventListener('click', () => {
                const name = btn.dataset.name || 'format';
                const table = btn.dataset.table || 'tabel';
                const firstConfirm = confirm(`Hapus semua isi data untuk format "${name}" (tabel ${table})?`);
                if (!firstConfirm) {
                    return;
                }

                const secondConfirm = prompt('Ketik KONFIRMASI untuk konfirmasi kedua:');
                if (!secondConfirm || secondConfirm.trim().toUpperCase() !== 'KONFIRMASI') {
                    alert('Penghapusan dibatalkan karena konfirmasi kedua tidak sesuai.');
                    return;
                }

                btn.closest('form').submit();
            });
        });

        // Export modal logic
        const exportModal = document.getElementById('exportModal');
        const exportForm = document.getElementById('exportForm');
        const exportTitle = document.getElementById('exportModalTitle');
        const showExport = () => {
            exportModal.classList.remove('hidden');
            exportModal.classList.add('flex');
        };
        const closeExport = () => {
            exportModal.classList.add('hidden');
            exportModal.classList.remove('flex');
        };

        window.openExportModal = (url, title) => {
            exportForm.setAttribute('action', url);
            exportTitle.textContent = title ? `Export Data - ${title}` : 'Export Data';
            showExport();
        };

        document.getElementById('btn-export-close')?.addEventListener('click', closeExport);
        document.getElementById('btn-export-cancel')?.addEventListener('click', closeExport);
        exportModal?.addEventListener('click', (e) => {
            if (e.target === exportModal) closeExport();
        });
    });

    // Handle Upload Confirmation (Dynamic Routing)
    document.body.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'btn-confirm-upload' || e.target.closest('#btn-confirm-upload'))) {
            const btn = e.target.id === 'btn-confirm-upload' ? e.target : e.target.closest('#btn-confirm-upload');
            const form = document.getElementById('uploadForm') || document.querySelector('form[action*="upload"]');
            
            if (!form) return;

            // Find selected mode
            const modeInput = document.querySelector('input[name="upload_mode"]:checked');
            if (!modeInput) {
                alert('Silakan pilih mode upload terlebih dahulu.');
                return;
            }

            e.preventDefault();
            const uploadMode = modeInput.value;
            
            // ROUTING LOGIC: Strict -> /upload/strict, Others -> /upload/process
            let targetUrl = '{{ route("upload.process") }}';
            if (uploadMode === 'strict') {
                targetUrl = '{{ route("upload.strict") }}';
            }

            const formData = new FormData(form);
            
            // UI Loading
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = 'Processing...';

            fetch(targetUrl, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Gagal: ' + (data.message || 'Error'));
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error(err);
                alert('Terjadi kesalahan sistem.');
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    });
</script>
