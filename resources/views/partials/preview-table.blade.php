<div class="border border-gray-200 rounded-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        {{-- Tabel ini tidak lagi memerlukan $excelHeaders karena tidak ada judul --}}
        <tbody class="bg-white divide-y divide-gray-200">
            @forelse($rows as $index => $row)
                {{-- Baris ini bisa diklik dan akan mengirim nomor barisnya (index + 1) --}}
                <tr class="cursor-pointer hover:bg-indigo-50 preview-row" data-row="{{ $index + 1 }}">
                    <td class="px-3 py-2 whitespace-nowrap text-sm font-bold text-gray-500 bg-gray-50 w-12 text-center">{{ $index + 1 }}</td>
                    @foreach($row as $cell)
                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-700">{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="px-6 py-4 text-center text-gray-500">Tidak ada data untuk ditampilkan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>