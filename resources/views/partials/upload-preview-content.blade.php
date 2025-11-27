<div class="space-y-4">
    <div class="bg-[#e8f1fb] border border-[#c7d9f3] rounded p-3">
        <p class="text-sm"><strong>Format:</strong> {{ $mapping->name }}</p>
        <p class="text-sm"><strong>Tabel Tujuan:</strong> {{ $mapping->table_name }}</p>
        <p class="text-sm"><strong>Baris Header:</strong> {{ $mapping->header_row }}</p>
    </div>

    <div>
        <h4 class="font-medium mb-2">Mapping Kolom:</h4>
        <div class="grid grid-cols-2 gap-2 text-sm">
            @foreach($mappingRules as $rule)
                <div class="flex items-center space-x-2 bg-gray-50 p-2 rounded">
                    <span class="font-mono bg-yellow-100 px-2 py-1 rounded text-xs">{{ $rule->excel_column }}</span>
                    <span>→</span>
                    <span class="font-mono bg-green-100 px-2 py-1 rounded text-xs">{{ $rule->database_column }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div>
        <h4 class="font-medium mb-2">Preview Data (5 baris pertama):</h4>
        <div class="overflow-x-auto border rounded max-h-96">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                        @foreach($headers as $header)
                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @foreach($previewRows as $index => $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ $index + 1 }}</td>
                            @foreach($row as $cell)
                                <td class="px-3 py-2 whitespace-nowrap">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p class="text-xs text-gray-500 mt-2">* Hanya menampilkan 5 baris pertama sebagai preview</p>
    </div>
</div>