<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">
                    {{ $isAdmin ? 'Aktivitas Pengguna' : 'Aktivitas Saya' }}
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    {{ $isAdmin ? 'Pilih pengguna untuk melihat riwayat upload & pembuatan format.' : 'Riwayat upload dan aksi yang pernah Anda lakukan.' }}
                </p>
                @if(!empty($search ?? ''))
                    <p class="mt-1 text-xs text-[#0057b7] font-semibold">Filter: "{{ $search }}"</p>
                @endif
            </div>
            <form method="GET" action="{{ route('logs.index') }}" class="flex items-center space-x-3" id="logFilterForm">
                @if($isAdmin)
                    @php
                        $selectedUser = $users->firstWhere('id', $selectedUserId);
                        $selectedUserLabel = $selectedUser ? ($selectedUser->name . ' (' . $selectedUser->email . ')') : '';
                    @endphp
                    <div class="relative w-72">
                        <input type="hidden" name="user_id" id="user_id_hidden" value="{{ $selectedUserId }}">
                        <input
                            type="text"
                            id="user_search_combo"
                            placeholder="Cari atau pilih pengguna..."
                            value="{{ $selectedUserLabel }}"
                            class="w-full pl-3 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm"
                            autocomplete="off"
                        />
                        <div id="user_dropdown" class="absolute z-30 w-full bg-white border border-gray-200 rounded-lg shadow-lg mt-1 max-h-56 overflow-auto hidden"></div>
                    </div>
                @endif
                <div class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search ?? '' }}"
                        placeholder="Cari file, format, mode..."
                        class="pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm w-64 md:w-72"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"></path>
                    </svg>
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-[#0057b7] text-white text-sm font-semibold rounded-lg shadow-sm hover:bg-[#004a99] focus:outline-none focus:ring-2 focus:ring-[#0057b7] focus:ring-offset-2">
                    Terapkan
                </button>
            </form>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-[#0057b7] via-[#006ad6] to-[#00a1e4] text-white flex items-center justify-between">
                    <div class="flex items-center space-x-3">
                        <div class="flex-shrink-0 bg-white/20 rounded-xl p-2">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold">Log Aktivitas</h3>
                        </div>
                    </div>
                    <div class="text-sm text-[#d8e7f7]">
                        Total: {{ number_format($logs->total()) }} entri
                    </div>
                </div>

                <div class="p-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Waktu</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Pengguna</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Format</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">File</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Mode</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Rows</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-100">
                            @forelse($logs as $log)
                                <tr class="hover:bg-[#f4f8fd] transition-colors duration-150">
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        {{ optional($log->created_at)->format('d M Y H:i') ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        {{ $log->user->name ?? '-' }}<br>
                                        <span class="text-xs text-gray-500">{{ $log->user->email ?? '' }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm font-semibold text-gray-900">
                                        @php
                                            $actionMap = [
                                                'upload' => 'Upload Data',
                                                'create_format' => 'Buat Format',
                                                'clear_data' => 'Hapus Isi',
                                                'delete_format' => 'Hapus Format',
                                            ];
                                            $actionLabel = $actionMap[$log->action ?? ''] ?? ucfirst($log->action ?? 'Upload Data');
                                        @endphp
                                        {{ $actionLabel }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        {{ $log->mappingIndex->description ?? $log->mappingIndex->code ?? '-' }}
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700">
                                        {{ $log->file_name }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        @php
                                            $mode = $log->upload_mode ?? ($log->action === 'upload' ? 'upsert' : null);
                                        @endphp
                                        @if($mode)
                                            <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-semibold bg-[#e8f1fb] text-[#0057b7] border border-[#c7d9f3]">
                                                {{ strtoupper($mode) }}
                                            </span>
                                        @else
                                            <span class="text-gray-400 text-xs">-</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-800">
                                        {{ number_format($log->rows_imported ?? 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold 
                                            {{ ($log->status ?? '') === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' }}">
                                            {{ ucfirst($log->status ?? 'unknown') }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-600">
                                        Belum ada aktivitas yang tercatat.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    <div class="mt-6">
                        {{ $logs->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($isAdmin)
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const combo = document.getElementById('user_search_combo');
            const dropdown = document.getElementById('user_dropdown');
            const hidden = document.getElementById('user_id_hidden');
            if (!combo || !dropdown || !hidden) return;

            const users = @json($users->map(fn($u) => ['id' => $u->id, 'label' => $u->name . ' (' . $u->email . ')']));

            const render = (term = '') => {
                const q = term.toLowerCase();
                const items = [];
                items.push('<button type="button" data-id="" data-label="" class="user-option w-full text-left px-3 py-2 hover:bg-[#f4f8fd] text-sm font-semibold text-gray-800">Semua pengguna</button>');
                users.forEach(u => {
                    if (q === '' || (u.label || '').toLowerCase().includes(q)) {
                        items.push(`<button type="button" data-id="${u.id}" data-label="${u.label.replace(/"/g, '&quot;')}" class="user-option w-full text-left px-3 py-2 hover:bg-[#f4f8fd] text-sm text-gray-800">${u.label}</button>`);
                    }
                });
                dropdown.innerHTML = items.join('') || '<div class="px-3 py-2 text-sm text-gray-500">Tidak ada hasil</div>';
            };

            const show = () => dropdown.classList.remove('hidden');
            const hide = () => setTimeout(() => dropdown.classList.add('hidden'), 150);

            combo.addEventListener('focus', () => { render(combo.value); show(); });
            combo.addEventListener('input', () => { render(combo.value); show(); });
            combo.addEventListener('blur', hide);

            dropdown.addEventListener('mousedown', (e) => {
                const btn = e.target.closest('.user-option');
                if (!btn) return;
                const id = btn.dataset.id || '';
                const label = btn.dataset.label || '';
                hidden.value = id;
                combo.value = label;
            });
        });
    </script>
    @endif
</x-app-layout>
