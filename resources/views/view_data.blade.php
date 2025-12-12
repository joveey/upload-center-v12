<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-4">
                <a href="{{ route('dashboard') }}" class="text-gray-600 hover:text-gray-900 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div class="flex-shrink-0 w-12 h-12 bg-[#0057b7] rounded-lg flex items-center justify-center shadow-sm">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                        Data: {{ $mapping->description }}
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Tabel: <span class="font-mono font-semibold">{{ $mapping->table_name }}</span>
                    </p>
                </div>
            </div>
            <div class="flex items-center space-x-3">
                <a href="{{ route('export.data', $mapping->id) }}" 
                   class="inline-flex items-center px-5 py-2.5 bg-green-600 border border-transparent rounded-lg font-bold text-sm text-white uppercase tracking-wide hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-all duration-200 shadow-sm hover:shadow-md group">
                    <svg class="w-5 h-5 mr-2 group-hover:-translate-y-1 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                    </svg>
                    Export Excel
                </a>
                <button type="button"
                        id="btn-open-trim"
                        class="inline-flex items-center px-4 py-2.5 bg-indigo-600 border border-transparent rounded-lg font-bold text-sm text-white uppercase tracking-wide hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-all duration-200 shadow-sm hover:shadow-md">
                    <span class="mr-2">✨</span>
                    Trim Spasi
                </button>
                @role('super-admin')
                    <form method="POST" action="{{ route('mapping.clear.data', $mapping->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="button"
                                class="inline-flex items-center px-4 py-2.5 bg-white/95 text-[#8c5800] border border-amber-200 hover:border-amber-300 hover:bg-amber-50 rounded-lg font-semibold text-sm tracking-wide focus:outline-none focus:ring-2 focus:ring-amber-200 transition-all duration-200 shadow-sm hover:shadow-md group btn-clear-data"
                                data-name="{{ $mapping->description ?? $mapping->code }}"
                                data-table="{{ $mapping->table_name }}">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-6 4h6m-8 4h10a2 2 0 002-2V7a2 2 0 00-2-2h-3.586a1 1 0 01-.707-.293l-1.414-1.414A1 1 0 0010.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                            </svg>
                            Hapus Isi
                        </button>
                    </form>
                    <form method="POST" action="{{ route('mapping.destroy', $mapping->id) }}">
                        @csrf
                        @method('DELETE')
                        <button type="button" 
                                class="inline-flex items-center px-4 py-2.5 bg-gradient-to-r from-[#d7263d] to-[#b31217] hover:from-[#b31217] hover:to-[#8f0e12] border border-transparent rounded-lg font-bold text-sm text-white tracking-wide focus:outline-none focus:ring-2 focus:ring-red-300 transition-all duration-200 shadow-md hover:shadow-lg group btn-delete-format"
                                data-name="{{ $mapping->description ?? $mapping->code }}">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5-4h4a1 1 0 011 1v1H9V4a1 1 0 011-1z"></path>
                            </svg>
                            Hapus Format
                        </button>
                    </form>
                @endrole
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-screen-2xl mx-auto px-4 sm:px-8 lg:px-12">
            <!-- Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-[#e0ebf9] rounded-lg p-3">
                                <svg class="w-6 h-6 text-[#0057b7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Baris</p>
                                <p class="text-2xl font-bold text-gray-900">{{ number_format($data->total()) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-100 rounded-lg p-3">
                                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Total Kolom</p>
                                <p class="text-2xl font-bold text-gray-900">{{ count($columns) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-gray-200">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-100 rounded-lg p-3">
                                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-medium text-gray-600">Halaman</p>
                                <p class="text-2xl font-bold text-gray-900">{{ $data->currentPage() }} / {{ $data->lastPage() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Table -->
            <div class="bg-white overflow-hidden shadow-lg rounded-lg border border-gray-200">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-[#0057b7]">
                            <tr>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider sticky left-0 bg-[#0057b7]">
                                    ID
                                </th>
                                @foreach($columnMapping as $excelCol => $dbCol)
                                    <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                                        <div class="flex flex-col">
                                            <span class="text-[#d8e7f7]">{{ $excelCol }}</span>
                                            <span class="font-semibold mt-1">{{ ucwords(str_replace('_', ' ', $dbCol)) }}</span>
                                        </div>
                                    </th>
                                @endforeach
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                                    Dibuat
                                </th>
                                <th scope="col" class="px-6 py-4 text-left text-xs font-bold text-white uppercase tracking-wider">
                                    Diperbarui
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @forelse($data as $row)
                                <tr class="hover:bg-[#e8f1fb] transition-colors">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 sticky left-0 bg-gray-50">
                                        #{{ $row->id }}
                                    </td>
                                    @foreach($columns as $column)
                                        <td class="px-6 py-4 text-sm text-gray-700">
                                            <div class="max-w-xs truncate" title="{{ $row->{$column} ?? '-' }}">
                                                {{ $row->{$column} ?? '-' }}
                                            </div>
                                        </td>
                                    @endforeach
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $row->created_at ? \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $row->updated_at ? \Carbon\Carbon::parse($row->updated_at)->format('d/m/Y H:i') : '-' }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($columns) + 3 }}" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <svg class="w-16 h-16 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                            </svg>
                                            <p class="text-gray-500 font-medium">Tidak ada data</p>
                                            <p class="text-sm text-gray-400 mt-1">Belum ada data yang diupload untuk format ini</p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($data->hasPages())
                    <div class="bg-gray-50 px-6 py-4 border-t border-gray-200">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Menampilkan <span class="font-semibold">{{ $data->firstItem() }}</span> sampai 
                                <span class="font-semibold">{{ $data->lastItem() }}</span> dari 
                                <span class="font-semibold">{{ $data->total() }}</span> baris
                            </div>
                            <div>
                                {{ $data->links() }}
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

<!-- Trim Modal -->
<div id="trimModal" class="fixed inset-0 z-50 hidden items-center justify-center">
    <div class="absolute inset-0 bg-black/50"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl max-w-lg w-full mx-4 p-6">
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-xl font-bold text-gray-900">Bersihkan Spasi Kosong</h3>
                <p class="text-sm text-gray-600 mt-1">Pilih kolom yang ingin dibersihkan dari spasi berlebih di awal/akhir teks.</p>
            </div>
            <button type="button" id="btn-close-trim" class="text-gray-500 hover:text-gray-800">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <form id="trimForm">
            <input type="hidden" name="period_date" value="{{ $period_date ?? '' }}">
            <div class="max-h-72 overflow-y-auto space-y-3 mb-6 border border-gray-200 rounded-lg p-3">
                @foreach($columns as $col)
                    <label class="flex items-center space-x-3">
                        <input type="checkbox" class="trim-column rounded text-indigo-600 focus:ring-indigo-500" name="columns[]" value="{{ $col }}" checked>
                        <span class="text-sm text-gray-800 font-medium">{{ ucwords(str_replace('_', ' ', $col)) }}</span>
                        <span class="ml-auto text-xs text-gray-500 font-mono">{{ $col }}</span>
                    </label>
                @endforeach
            </div>
            <div class="flex items-center justify-end space-x-3">
                <button type="button" id="btn-cancel-trim" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</button>
                <button type="submit" id="btn-trim-submit" class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg">Bersihkan</button>
            </div>
        </form>
    </div>
    <div class="absolute inset-0 hidden" id="trimModalCloser"></div>
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

        const trimModal = document.getElementById('trimModal');
        const openTrimBtn = document.getElementById('btn-open-trim');
        const closeTrimBtn = document.getElementById('btn-close-trim');
        const cancelTrimBtn = document.getElementById('btn-cancel-trim');
        const trimForm = document.getElementById('trimForm');
        const trimSubmit = document.getElementById('btn-trim-submit');

        const toggleTrimModal = (show) => {
            if (show) {
                trimModal.classList.remove('hidden');
                trimModal.classList.add('flex');
            } else {
                trimModal.classList.add('hidden');
                trimModal.classList.remove('flex');
            }
        };

        openTrimBtn?.addEventListener('click', () => toggleTrimModal(true));
        closeTrimBtn?.addEventListener('click', () => toggleTrimModal(false));
        cancelTrimBtn?.addEventListener('click', () => toggleTrimModal(false));
        trimModal?.addEventListener('click', (e) => {
            if (e.target === trimModal) toggleTrimModal(false);
        });

        trimForm?.addEventListener('submit', (e) => {
            e.preventDefault();
            const checked = Array.from(document.querySelectorAll('.trim-column:checked')).map(cb => cb.value);
            if (checked.length === 0) {
                alert('Pilih minimal satu kolom untuk dibersihkan.');
                return;
            }

            const formData = new FormData(trimForm);
            trimSubmit.disabled = true;
            const originalText = trimSubmit.textContent;
            trimSubmit.textContent = 'Memproses...';

            fetch("{{ route('mapping.clean.data', $mapping->id) }}", {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').getAttribute('content'),
                    'Accept': 'application/json'
                }
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message || 'Gagal membersihkan spasi.');
                    }
                })
                .catch(() => {
                    alert('Terjadi kesalahan saat membersihkan spasi.');
                })
                .finally(() => {
                    trimSubmit.disabled = false;
                    trimSubmit.textContent = originalText;
                    toggleTrimModal(false);
                });
        });
    });
</script>
