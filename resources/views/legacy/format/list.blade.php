<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">Legacy Data Mappings</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Pilih mapping legacy untuk melihat data dari koneksi/tabel terkait.
                </p>
                @if(!empty($search ?? ''))
                    <p class="mt-1 text-xs text-[#0057b7] font-semibold">Filter: "{{ $search }}"</p>
                @endif
            </div>
            <div class="flex items-center space-x-3">
                <form method="GET" action="{{ route('legacy.format.list') }}" class="relative">
                    <input
                        type="text"
                        name="q"
                        value="{{ $search ?? '' }}"
                        placeholder="Cari kode, deskripsi, tabel..."
                        class="pl-10 pr-3 py-2.5 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-[#0057b7]/40 focus:border-[#0057b7] bg-white shadow-sm w-64 md:w-72"
                    />
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 transform -translate-y-1/2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M11 19a8 8 0 100-16 8 8 0 000 16z"></path>
                    </svg>
                </form>
                <div class="flex items-center space-x-2 bg-[#e8f1fb] text-[#0057b7] px-3 py-2 rounded-lg border border-[#c7d9f3]">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-2.21 0-4 1.79-4 4s1.79 4 4 4m0-8c2.21 0 4 1.79 4 4s-1.79 4-4 4m0-8V4m0 12v4m4-8h4m-8 0H4"/>
                    </svg>
                    <span class="text-sm font-semibold">Visible to all authenticated users</span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-xl rounded-2xl border border-gray-200 overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-[#0057b7] via-[#006ad6] to-[#00a1e4] text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 bg-white/20 rounded-xl p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-[#d8e7f7]">Legacy / Mapping-driven</p>
                                <h3 class="text-xl font-semibold">Daftar Mapping Legacy</h3>
                            </div>
                        </div>
                        <div class="text-sm text-[#d8e7f7]">
                            Klik salah satu untuk membuka data.
                        </div>
                    </div>
                </div>

                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Code</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Deskripsi</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Tabel</th>
                                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse($mappings as $mapping)
                                    <tr class="hover:bg-[#f4f8fd] transition-colors duration-150">
                                        <td class="px-4 py-3 text-sm font-semibold text-gray-900">{{ $mapping->code }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-800">{{ $mapping->description }}</td>
                                        <td class="px-4 py-3 text-sm text-gray-700 font-mono">{{ $mapping->table_name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-sm">
                                            <a href="{{ route('legacy.format.index', $mapping->id) }}" class="inline-flex items-center px-3 py-1.5 bg-[#0057b7] hover:bg-[#004a99] text-white rounded-lg text-xs font-semibold shadow-sm transition-colors duration-150">
                                                Buka
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-6 text-center text-sm text-gray-600">
                                            Belum ada mapping legacy yang terdaftar.
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
