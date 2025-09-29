<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Menampilkan pesan sukses -->
            @if (session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                    <p class="font-bold">Sukses</p>
                    <p>{{ session('success') }}</p>
                </div>
            @endif

            <!-- Form Upload Cepat -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Unggah Data (Alur Cepat)
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">
                        Pilih file laporan dan format yang sesuai untuk mengimpor data secara otomatis.
                    </p>

                    <form action="{{ route('upload.process') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Input File -->
                            <div>
                                <label for="data_file" class="block text-sm font-medium text-gray-700">File Laporan</label>
                                <input type="file" name="data_file" id="data_file" required
                                       accept=".xlsx, .xls"
                                       class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                            </div>

                            <!-- Pilih Format -->
                            <div>
                                <label for="mapping_id" class="block text-sm font-medium text-gray-700">Gunakan Aturan Format</label>
                                <select name="mapping_id" id="mapping_id" required
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                        @if($mappings->isEmpty()) disabled @endif>
                                    @if($mappings->isEmpty())
                                        <option>Belum ada format terdaftar</option>
                                    @else
                                        <option value="">-- Pilih Format --</option>
                                        @foreach($mappings as $mapping)
                                            <option value="{{ $mapping->id }}">{{ $mapping->name }}</option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        </div>

                        <!-- Tombol Submit -->
                        <div class="flex items-center justify-end mt-6">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150"
                                    @if($mappings->isEmpty()) disabled @endif>
                                Proses & Impor Data
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabel Daftar Format Terdaftar -->
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Format Laporan Terdaftar
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Deskripsi Format</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @forelse($mappings as $mapping)
                                <tr>
                                    <td class="px-6 py-4 text-sm text-gray-500">{{ $mapping->id }}</td>
                                    <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $mapping->name }}</td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="2" class="px-6 py-4 text-sm text-center text-gray-500">
                                        Belum ada format yang Anda daftarkan. Silakan <a href="{{ route('mapping.register.form') }}" class="text-indigo-600 hover:text-indigo-900">daftarkan format baru</a>.
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>