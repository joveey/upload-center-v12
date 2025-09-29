<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Daftarkan Format Laporan Baru') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">
                        Langkah 1: Unggah Contoh File
                    </h3>

                    <!-- Pastikan form memiliki enctype untuk file upload -->
                    <form action="{{ route('mapping.register.process') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <!-- Deskripsi Format -->
                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Deskripsi Format</label>
                            <input type="text" name="name" id="name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Contoh: Laporan Penjualan Bulanan V2">
                            <p class="mt-2 text-sm text-gray-500">Beri nama yang mudah dikenali untuk format laporan ini.</p>
                        </div>

                        <!-- Input File Excel -->
                        <div class="mb-4">
                            <label for="excel_file" class="block text-sm font-medium text-gray-700">File Excel</label>
                            <input type="file" name="excel_file" id="excel_file" required
                                   accept=".xlsx, .xls"
                                   class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                            <p class="mt-2 text-sm text-gray-500">Unggah satu file contoh dengan format yang ingin Anda daftarkan.</p>
                        </div>

                        <!-- Nomor Baris Header -->
                        <div class="mb-4">
                            <label for="header_row" class="block text-sm font-medium text-gray-700">Nomor Baris Header</label>
                            <input type="number" name="header_row" id="header_row" required value="1" min="1"
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-2 text-sm text-gray-500">Baris ke berapa di file Excel Anda yang berisi judul kolom (header)?</p>
                        </div>

                        <!-- Tombol Submit -->
                        <div class="flex items-center justify-end mt-6">
                            <button type="submit"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Lanjutkan ke Langkah Mapping
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>