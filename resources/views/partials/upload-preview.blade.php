<h1 class="text-xl font-medium text-gray-800">Pratinjau & Pemetaan Kolom</h1>
<p class="mt-2 text-sm text-gray-500">
    Pilih baris header dan cocokkan kolom Excel dengan kolom format.
</p>

<div class="mt-4">
    <x-input-label for="header_row" value="Data dimulai setelah baris header nomor:" />
    <x-text-input id="header_row" name="header_row" type="number" value="1" class="mt-1 block w-1/4" min="1" />
</div>

<div class="mt-6 space-y-4 max-h-64 overflow-y-auto pr-4">
    @foreach($excelHeaders as $header)
        <div class="grid grid-cols-12 gap-4 items-center">
            <div class="col-span-5">
                <label class="block text-sm font-medium text-gray-700">
                    {{ $header }} <span class="text-gray-400">(Kolom Excel)</span>
                </label>
            </div>
            <div class="col-span-7">
                <select name="mappings[{{ $header }}]" class="block w-full mt-1 border-gray-300 rounded-md shadow-sm">
                    <option value="">-- Jangan Impor --</option>
                    @foreach($formatColumns as $dbCol)
                        <option value="{{ $dbCol }}" @if(strtolower(str_replace('_', '', $dbCol)) == strtolower(str_replace(' ', '', $header))) selected @endif>
                            {{ $dbCol }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    @endforeach
</div>

<div class="mt-6">
    <h3 class="text-lg font-medium">Pratinjau Data</h3>
    <div class="mt-2 overflow-x-auto border rounded-lg max-h-64">
        <table class="min-w-full divide-y">
            <thead class="bg-gray-50">
                <tr>
                    @foreach($excelHeaders as $header)
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="bg-white divide-y">
                @foreach($previewRows->slice(1) as $row) {{-- Skip header --}}
                    <tr>
                        @foreach($row as $cell)
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 truncate max-w-xs">{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="flex justify-end mt-6 border-t pt-6">
    <x-secondary-button type="button" @click="showModal = false">Batal</x-secondary-button>
    <x-primary-button type="submit" class="ml-4" onclick="this.form.submit()">Impor Data</x-primary-button>
</div>