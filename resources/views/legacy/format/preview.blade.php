<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-bold text-2xl text-gray-900 leading-tight">Preview Data Legacy</h2>
                <p class="mt-1 text-sm text-gray-600">
                    Table: <code class="text-xs">{{ $tableName }}</code>
                    @if(!empty($legacyDbName ?? ''))
                        <span class="ml-2 text-xs text-gray-500">DB: {{ $legacyDbName }}</span>
                    @endif
                </p>
                @if(!empty($search ?? ''))
                    <p class="mt-1 text-xs text-[#0057b7] font-semibold">Filter: "{{ $search }}"</p>
                @endif
            </div>
            <div class="flex items-center space-x-2">
                <a href="{{ route('legacy.format.list', ['db' => $selectedDb]) }}" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-150">
                    Kembali
                </a>
                @can('create format')
                    <form action="{{ route('legacy.format.quick-map') }}" method="POST" class="inline">
                        @csrf
                        <input type="hidden" name="table_name" value="{{ $tableName }}">
                        <input type="hidden" name="db" value="{{ $selectedDb }}">
                        <button type="submit" class="inline-flex items-center px-4 py-2.5 bg-[#0057b7] hover:bg-[#004a99] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-150">
                            Register Table
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl rounded-2xl border border-gray-200">
                <div class="px-6 py-5 border-b border-gray-100 bg-gradient-to-r from-[#0057b7] via-[#006ad6] to-[#00a1e4] text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0 bg-white/20 rounded-xl p-2">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v8m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V9a2 2 0 012-2h2a2 2 0 012 2v8a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm text-[#d8e7f7]">Legacy / Preview</p>
                                <h3 class="text-xl font-semibold">Data untuk {{ $tableName }}</h3>
                            </div>
                        </div>
                        <div class="text-sm text-[#d8e7f7]">
                            Preview data sebelum registrasi format.
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-4">
                    <form method="GET" action="{{ route('legacy.format.preview') }}" class="flex flex-col md:flex-row md:items-center md:space-x-3 space-y-3 md:space-y-0">
                        <input type="hidden" name="table" value="{{ $tableName }}">
                        <div class="flex-1">
                            <div class="relative">
                                <input
                                    type="text"
                                    name="q"
                                    value="{{ $search }}"
                                    class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-[#0057b7] focus:ring focus:ring-[#d8e7f7] focus:ring-opacity-50 pl-10 py-2.5"
                                    placeholder="Cari di kolom teks..."
                                >
                                <div class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 105 11a6 6 0 0012 0z"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="w-full md:w-60">
                            <select name="db" onchange="this.form.submit()" class="w-full rounded-lg border-gray-300 focus:border-[#0057b7] focus:ring focus:ring-[#d8e7f7] focus:ring-opacity-50 py-2.5">
                                @foreach(($legacyDatabases ?? []) as $dbName)
                                    <option value="{{ $dbName }}" @selected($dbName === ($selectedDb ?? ''))>{{ $dbName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex space-x-2">
                            <a href="{{ route('legacy.format.preview', ['table' => $tableName, 'db' => $selectedDb]) }}" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-150">
                                Reset
                            </a>
                            <button class="inline-flex items-center px-5 py-2.5 bg-[#0057b7] hover:bg-[#004a99] text-white rounded-lg text-sm font-semibold shadow-sm transition-colors duration-150" type="submit">
                                Tampilkan
                            </button>
                        </div>
                    </form>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    @if($showIdColumn)
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">ID</th>
                                    @endif
                                    @foreach($columns as $column)
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase tracking-wider">
                                            {{ $column['label'] }}
                                        </th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-100">
                                @forelse ($data as $row)
                                    <tr class="hover:bg-[#f4f8fd] transition-colors duration-150">
                                        @if($showIdColumn)
                                            <td class="px-4 py-3 text-sm text-gray-900 font-semibold">{{ $row->id }}</td>
                                        @endif
                                        @foreach($columns as $column)
                                            <td class="px-4 py-3 text-sm text-gray-800">
                                                {{ data_get($row, $column['name']) }}
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ count($columns) + ($showIdColumn ? 1 : 0) }}" class="px-4 py-6 text-center text-sm text-gray-600">
                                            Tidak ada data legacy untuk tabel ini.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-2">
                        {{ $data->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
