<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <p class="uppercase tracking-[0.08em] text-[11px] font-bold text-[#0057b7]">Existing Data</p>
                    <h2 class="font-black text-3xl text-gray-900 leading-tight">Daftar Tabel Existing</h2>
                    <p class="mt-1 text-sm text-gray-600">Hanya menampilkan tabel yang belum diregister. Pilih server & database untuk difilter.</p>
                </div>
            </div>
            @php
                $serverFallback = array_values(array_filter(array_map('trim', explode(',', env('DATABASES_SERVERS', '')))));
                $selectedServer = request('server', '');
                $databaseFallback = array_values(array_filter(array_map('trim', explode(',', env('LEGACY_DB_DATABASES', '')))));
            @endphp
            <form method="GET" action="{{ route('legacy.format.list') }}" class="w-full flex flex-wrap items-center gap-3 bg-white/90 backdrop-blur border border-gray-200 rounded-xl px-4 py-4 shadow-sm" id="existing-filter-form">
                <div class="relative flex-1 min-w-[260px]">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search ?? '' }}"
                        placeholder="Cari tabel, kode, deskripsi..."
                        class="pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm w-full"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"></path>
                    </svg>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <select id="serverSelect" name="server" class="min-w-[140px] border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm py-2.5 px-3">
                        <option value="">Server</option>
                        @foreach($serverFallback as $srv)
                            <option value="{{ $srv }}" @selected($srv === $selectedServer)>{{ $srv }}</option>
                        @endforeach
                    </select>
                    <select id="databaseSelect" name="db" class="min-w-[180px] border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm py-2.5 px-3">
                        <option value="">{{ $selectedDb ?? 'Pilih server dulu' }}</option>
                    </select>
                </div>
                <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg bg-[#0057b7] text-white text-xs font-semibold hover:bg-[#004a99] transition shadow-sm">Terapkan</button>
            </form>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="max-w-screen-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-[#0f63f4] via-[#127dfc] to-[#22b0ff] text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 bg-white/20 rounded-xl p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-[#e5f2ff]/90">Existing / Unregistered tables</p>
                                <h3 class="text-xl font-semibold">Daftar Tabel Existing</h3>
                            </div>
                        </div>
                        <div class="text-sm text-[#e5f2ff]/90">
                            Tabel yang sudah diregister disembunyikan.
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Schema</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tabel</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Mapping</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse($mappings as $mapping)
                                    <tr class="hover:bg-[#f4f8fd] transition-colors duration-150">
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $mapping->schema }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $mapping->table_name }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800">
                                            @if($mapping->is_mapped)
                                                <div class="space-y-0.5">
                                                    <div class="flex items-center space-x-2 text-[#0b7b31]">
                                                        <span class="inline-flex items-center px-2 py-0.5 bg-[#e8f6ee] border border-[#c8e9d6] text-xs font-semibold rounded-md">Mapped</span>
                                                        <span class="text-xs text-gray-600">({{ $mapping->code }})</span>
                                                    </div>
                                                    <div class="text-xs text-gray-600 line-clamp-2">{{ $mapping->description }}</div>
                                                </div>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 bg-[#fff8e5] border border-[#f5e3b5] text-xs font-semibold rounded-md text-[#946200]">Belum dimapping</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm">
                                            @if($mapping->is_mapped)
                                                <a href="{{ route('legacy.format.index', $mapping->mapping_id) }}" class="inline-flex items-center px-3 py-1.5 bg-[#0057b7] hover:bg-[#004a99] text-white rounded-lg text-xs font-semibold shadow-sm transition-colors duration-150">
                                                    Buka
                                                </a>
                                            @else
                                                <div class="flex items-center flex-wrap gap-2">
                                                    <a href="{{ route('legacy.format.preview', ['table' => $mapping->table_name, 'db' => $selectedDb]) }}" class="inline-flex items-center px-3 py-1.5 bg-white border border-[#cbd5e1] hover:border-[#0057b7] hover:text-[#0057b7] text-gray-700 rounded-lg text-xs font-semibold shadow-sm transition-colors duration-150">
                                                        Preview
                                                    </a>
                                                    @can('create format')
                                                        <form action="{{ route('legacy.format.quick-map') }}" method="POST" class="inline">
                                                            @csrf
                                                            <input type="hidden" name="table_name" value="{{ $mapping->table_name }}">
                                                            <input type="hidden" name="db" value="{{ $selectedDb }}">
                                                            <button type="submit" class="inline-flex items-center px-3 py-1.5 bg-white border border-[#cbd5e1] hover:border-[#0057b7] hover:text-[#0057b7] text-gray-700 rounded-lg text-xs font-semibold shadow-sm transition-colors duration-150">
                                                                Register Table
                                                            </button>
                                                        </form>
                                                    @else
                                                        <span class="text-xs text-gray-500">Butuh izin buat format</span>
                                                    @endcan
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-600">
                                            Semua tabel existing sudah diregister atau tidak ditemukan.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($mappings instanceof \Illuminate\Pagination\LengthAwarePaginator)
                        <div class="mt-6">
                            {{ $mappings->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const serverSelect = document.getElementById('serverSelect');
    const databaseSelect = document.getElementById('databaseSelect');
    const form = document.getElementById('existing-filter-form');
    const selectedServer = "{{ request('server', '') }}";
    const selectedDb = "{{ $selectedDb ?? '' }}";
    const serverFallback = @json($serverFallback);
    const databaseFallback = @json($databaseFallback);

    async function loadServers() {
        try {
            const res = await fetch('/api/database-servers');
            const data = await res.json();
            let servers = data.servers || [];
            if ((!servers || servers.length === 0) && serverFallback.length) {
                servers = serverFallback.map(s => ({ id: s, name: s }));
            }
            serverSelect.innerHTML = '<option value=\"\">Server</option>';
            servers.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name || s.id;
                if (s.id == selectedServer) opt.selected = true;
                serverSelect.appendChild(opt);
            });
            if (selectedServer) {
                await loadDatabases(selectedServer, selectedDb);
            }
        } catch (e) {
            console.error('Gagal load server list', e);
        }
    }

    async function loadDatabases(serverId, preselect = '') {
        databaseSelect.innerHTML = '<option value=\"\">Loading...</option>';
        if (!serverId) {
            databaseSelect.innerHTML = '<option value=\"\">Pilih server dulu</option>';
            return;
        }
        try {
            const res = await fetch(`/api/database-servers/${encodeURIComponent(serverId)}/databases?server=${encodeURIComponent(serverId)}`);
            const data = await res.json();
            let dbs = data.databases || [];
            if ((!dbs || dbs.length === 0) && databaseFallback.length) {
                dbs = databaseFallback.map(d => ({ id: d, name: d }));
            }
            databaseSelect.innerHTML = '';
            if (dbs.length === 0) {
                databaseSelect.innerHTML = '<option value=\"\">Database tidak ditemukan</option>';
            } else {
                dbs.forEach(db => {
                    const opt = document.createElement('option');
                    opt.value = db.id;
                    opt.textContent = db.name || db.id;
                    if (db.id == preselect) opt.selected = true;
                    databaseSelect.appendChild(opt);
                });
            }
        } catch (e) {
            console.error('Gagal load database list', e);
            databaseSelect.innerHTML = '<option value=\"\">Gagal load</option>';
        }
    }

    serverSelect.addEventListener('change', (e) => {
        loadDatabases(e.target.value, '');
    });

    loadServers().then(() => {
        // Ensure database dropdown is clickable
        databaseSelect.disabled = false;
    });
});
</script>
@endpush
