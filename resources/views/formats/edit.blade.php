<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <a href="{{ route('formats.index') }}" class="text-gray-600 hover:text-gray-900">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                <div>
                    <p class="text-sm text-gray-500">Edit Format</p>
                    <h2 class="font-bold text-2xl text-gray-900 leading-tight">{{ $mapping->description ?? $mapping->code }}</h2>
                    <p class="text-xs text-gray-500 mt-1">Tabel: <span class="font-mono font-semibold">{{ $mapping->table_name }}</span></p>
                </div>
            </div>
            <div class="flex items-center space-x-2">
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">ID: {{ $mapping->id }}</span>
                <span class="px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">Kolom: {{ $mapping->columns->count() }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
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

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
                    <form method="POST" action="{{ route('mapping.update', $mapping->id) }}" id="editForm">
                        @csrf
                        @method('PUT')

                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-800 mb-1">Header Row</label>
                            <input type="number" name="header_row" value="{{ old('header_row', $mapping->header_row) }}" min="1" required
                                   class="w-full rounded-lg border-gray-300 focus:border-[#0057b7] focus:ring focus:ring-[#0057b7]/30 shadow-sm">
                            <p class="text-xs text-gray-500 mt-1">Baris header di file Excel (default: {{ $mapping->header_row }}).</p>
                        </div>

                        <div class="mb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-bold text-gray-900">Tambah Kolom Baru</h3>
                                <p class="text-sm text-gray-600">Kolom Excel diisi otomatis berurutan setelah kolom terakhir.</p>
                            </div>
                            <button type="button" id="btn-add-row" class="inline-flex items-center px-3 py-2 bg-[#0057b7] text-white text-sm font-semibold rounded-lg hover:bg-[#004a99] shadow-sm">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                Tambah Baris
                            </button>
                        </div>

                        <div class="space-y-3" id="columnRows">
                            @php $oldColumns = old('columns', []); @endphp
                            @foreach($oldColumns as $idx => $col)
                                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 border border-gray-200 rounded-lg p-3 bg-gray-50">
                                    <div class="md:col-span-8">
                                        <label class="block text-xs font-semibold text-gray-700 mb-1">Kolom Database</label>
                                        <input type="text" name="columns[{{ $idx }}][database_column]" value="{{ $col['database_column'] ?? '' }}" class="w-full rounded-lg border-gray-300 focus:border-[#0057b7] focus:ring-[#0057b7]/30 text-sm lowercase">
                                    </div>
                                    <div class="md:col-span-3 flex items-center">
                                        <label class="inline-flex items-center text-xs font-semibold text-gray-700 space-x-2">
                                            <input type="checkbox" name="columns[{{ $idx }}][is_unique_key]" value="1" {{ !empty($col['is_unique_key']) ? 'checked' : '' }} class="rounded text-[#0057b7] focus:ring-[#0057b7]">
                                            <span>Tandai Unique Key</span>
                                        </label>
                                    </div>
                                    <div class="md:col-span-1 flex justify-end">
                                        <button type="button" class="btn-remove-row text-red-600 hover:text-red-800 text-sm font-semibold">Hapus</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-6 flex items-center justify-end space-x-3">
                            <a href="{{ route('formats.index') }}" class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 rounded-lg">Batal</a>
                            <button type="submit" class="px-5 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-[#0057b7] to-[#00a1e4] hover:from-[#004a99] hover:to-[#0091cf] rounded-lg shadow-sm">Simpan Perubahan</button>
                        </div>
                    </form>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-3">Kolom Saat Ini</h3>
                    <div class="border border-gray-200 rounded-lg overflow-hidden">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-gray-700 font-semibold">
                                <tr>
                                    <th class="px-3 py-2 text-left">Excel</th>
                                    <th class="px-3 py-2 text-left">Database</th>
                                    <th class="px-3 py-2 text-left">Unique</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @foreach($columns as $col)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-700">{{ $col->excel_column_index }}</td>
                                        <td class="px-3 py-2 font-mono text-xs text-gray-800">{{ $col->table_column_name }}</td>
                                        <td class="px-3 py-2">
                                            @if($col->is_unique_key)
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">Ya</span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-700">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="text-xs text-gray-500 mt-3">Kolom lama tidak diubah. Upload berikutnya otomatis membawa kolom baru.</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const rowsWrap = document.getElementById('columnRows');
        const addBtn = document.getElementById('btn-add-row');

        const buildRow = (idx) => {
            const div = document.createElement('div');
            div.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 border border-gray-200 rounded-lg p-3 bg-gray-50';
            div.innerHTML = `
                <div class="md:col-span-8">
                    <label class="block text-xs font-semibold text-gray-700 mb-1">Kolom Database</label>
                    <input type="text" name="columns[${idx}][database_column]" class="w-full rounded-lg border-gray-300 focus:border-[#0057b7] focus:ring-[#0057b7]/30 text-sm lowercase" placeholder="nama_kolom">
                </div>
                <div class="md:col-span-3 flex items-center">
                    <label class="inline-flex items-center text-xs font-semibold text-gray-700 space-x-2">
                        <input type="checkbox" name="columns[${idx}][is_unique_key]" value="1" class="rounded text-[#0057b7] focus:ring-[#0057b7]">
                        <span>Tandai Unique Key</span>
                    </label>
                </div>
                <div class="md:col-span-1 flex justify-end">
                    <button type="button" class="btn-remove-row text-red-600 hover:text-red-800 text-sm font-semibold">Hapus</button>
                </div>
            `;
            return div;
        };

        addBtn.addEventListener('click', () => {
            const idx = rowsWrap.querySelectorAll('.grid').length;
            rowsWrap.appendChild(buildRow(idx));
        });

        rowsWrap.addEventListener('click', (e) => {
            if (e.target.classList.contains('btn-remove-row')) {
                e.preventDefault();
                const row = e.target.closest('.grid');
                if (row) row.remove();
            }
        });
    });
</script>
