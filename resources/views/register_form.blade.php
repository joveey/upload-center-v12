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

                    {{-- Form sekarang memiliki ID agar bisa ditarget oleh JavaScript --}}
                    <form id="uploadForm" action="{{ route('mapping.register.process') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium text-gray-700">Deskripsi Format</label>
                            <input type="text" name="name" id="name" required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                   placeholder="Contoh: Laporan Penjualan Bulanan V2">
                        </div>

                        <div class="mb-4">
                            <label for="excel_file" class="block text-sm font-medium text-gray-700">File Excel</label>
                            <input type="file" name="excel_file" id="excel_file" required
                                   accept=".xlsx, .xls"
                                   class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none">
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <button type="button" id="previewButton"
                                    class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Lanjutkan & Pilih Header
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <x-modal name="preview-modal" :show="false" maxWidth="6xl" :closeable="true">
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900">
                Pratinjau File: Pilih Baris Header
            </h2>
            <p class="mt-1 text-sm text-gray-600">
                Klik pada salah satu nomor baris (di kolom paling kiri) untuk menandainya sebagai baris header (judul kolom). Ini akan melanjutkan proses ke langkah mapping.
            </p>

            <div class="mt-6 overflow-auto max-h-[60vh]" id="preview-content">
                {{-- Konten pratinjau akan dimuat di sini oleh JavaScript --}}
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Batal
                </x-secondary-button>
            </div>
        </div>
    </x-modal>

    {{-- Tambahkan meta tag CSRF token untuk AJAX request --}}
    @push('meta')
        <meta name="csrf-token" content="{{ csrf_token() }}">
    @endpush

    {{-- SCRIPT UNTUK MENGONTROL MODAL --}}
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const previewButton = document.getElementById('previewButton');
            const uploadForm = document.getElementById('uploadForm');
            const fileInput = document.getElementById('excel_file');
            const nameInput = document.getElementById('name');
            const previewContent = document.getElementById('preview-content');

            previewButton.addEventListener('click', function () {
                if (!nameInput.value) {
                    alert('Silakan isi Deskripsi Format terlebih dahulu.');
                    nameInput.focus();
                    return;
                }
                if (!fileInput.files.length) {
                    alert('Silakan pilih file Excel terlebih dahulu.');
                    fileInput.focus();
                    return;
                }

                let formData = new FormData(uploadForm);

                previewContent.innerHTML = `<div class="text-center py-10">Memuat pratinjau...</div>`;
                window.dispatchEvent(new CustomEvent('open-modal', { detail: 'preview-modal' }));

                fetch('{{ route("mapping.preview") }}', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'text/html',
                    }
                })
                .then(response => response.text())
                .then(html => {
                    previewContent.innerHTML = html;
                    attachRowClickHandlers();
                })
                .catch(error => {
                    console.error('Error:', error);
                    previewContent.innerHTML = `<p class="text-red-500 text-center py-10">Gagal memuat pratinjau. Silakan periksa konsol untuk detail.</p>`;
                    window.dispatchEvent(new CustomEvent('close-modal', { detail: 'preview-modal' }));
                });
            });

            function attachRowClickHandlers() {
                document.querySelectorAll('.preview-row').forEach(row => {
                    row.addEventListener('click', function() {
                        const headerRow = this.dataset.row;

                        const hiddenInput = document.createElement('input');
                        hiddenInput.type = 'hidden';
                        hiddenInput.name = 'header_row';
                        hiddenInput.value = headerRow;
                        uploadForm.appendChild(hiddenInput);

                        uploadForm.submit();
                    });
                });
            }
        });
    </script>
    @endpush
</x-app-layout>