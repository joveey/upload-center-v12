<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                @if (session('success'))
                    <div class="p-4 text-sm text-green-700 bg-green-100 rounded-lg" role="alert">
                        <span class="font-medium">Sukses!</span> {{ session('success') }}
                    </div>
                @endif
                @if (session('error'))
                    <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                         <span class="font-medium">Error!</span> {{ session('error') }}
                    </div>
                @endif
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <div>
                        <h3 class="font-semibold text-lg text-gray-800 border-b pb-2 mb-4">
                            Unggah Laporan
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            Gunakan form ini untuk mengimpor data Excel menggunakan format yang sudah terdaftar.
                        </p>
                        
                        <form id="uploadForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="mapping_id" :value="__('Pilih Format Laporan')" />
                                <select name="mapping_id" id="mapping_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">-- Harap Pilih --</option>
                                    @forelse($mappings as $mapping)
                                        <option value="{{ $mapping->id }}">
                                            {{ $mapping->description ?? $mapping->code }}
                                        </option>
                                    @empty
                                        <option value="" disabled>Belum ada format terdaftar</option>
                                    @endforelse
                                </select>
                            </div>
                            <div>
                                <x-input-label for="data_file" :value="__('Pilih File Excel')" />
                                <input type="file" name="data_file" id="data_file" accept=".xlsx,.xls" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" required>
                            </div>
                            <div>
                                <x-primary-button type="button" id="previewButton" :disabled="$mappings->isEmpty()">
                                    {{ __('Preview & Konfigurasi') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>

                    <div>
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                           <h3 class="font-semibold text-lg text-gray-800">
                                Manajemen Format
                            </h3>
                            @can('register format')
                                <a href="{{ route('mapping.register.form') }}">
                                    <x-secondary-button>
                                        + Buat Format Baru
                                    </x-secondary-button>
                                </a>
                            @endcan
                        </div>
                        <div class="border rounded-lg max-h-60 overflow-y-auto">
                            <ul class="divide-y divide-gray-200">
                                @forelse ($mappings as $mapping)
                                    <li class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900">
                                            {{ $mapping->description ?? $mapping->code }}
                                        </p>
                                        <p class="text-xs text-gray-500">Tabel: {{ $mapping->table_name }}</p>
                                        <p class="text-xs text-gray-500">
                                            Kolom: {{ $mapping->columns->pluck('table_column_name')->implode(', ') }}
                                        </p>
                                    </li>
                                @empty
                                    <li class="px-4 py-4 text-sm text-gray-500 text-center">
                                        Belum ada format yang terdaftar.
                                    </li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Modal Preview dengan Mapping Interaktif --}}
    <div id="previewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 mx-auto p-5 border w-11/12 max-w-7xl shadow-lg rounded-md bg-white max-h-[90vh] overflow-y-auto">
            <div class="mt-3">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Preview & Konfigurasi Import Data</h3>
                    <button id="closeModalX" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="previewContent" class="mb-4">
                    <div class="text-center py-8">
                        <svg class="animate-spin h-8 w-8 mx-auto text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <p class="mt-2 text-gray-600">Loading preview...</p>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 border-t pt-4">
                    <x-secondary-button type="button" id="closeModal">
                        Batal
                    </x-secondary-button>
                    <x-primary-button type="button" id="confirmUpload">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Upload Data
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let previewData = null;

        document.addEventListener('DOMContentLoaded', function() {
            const previewButton = document.getElementById('previewButton');
            const confirmUpload = document.getElementById('confirmUpload');
            const closeModal = document.getElementById('closeModal');
            const closeModalX = document.getElementById('closeModalX');
            const modal = document.getElementById('previewModal');
            const form = document.getElementById('uploadForm');

            // Show preview
            previewButton.addEventListener('click', function() {
                const mappingId = document.getElementById('mapping_id').value;
                const fileInput = document.getElementById('data_file');
                
                if (!mappingId) {
                    alert('Pilih format terlebih dahulu');
                    return;
                }
                
                if (!fileInput.files[0]) {
                    alert('Pilih file terlebih dahulu');
                    return;
                }

                modal.classList.remove('hidden');

                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('mapping_id', mappingId);
                formData.append('data_file', fileInput.files[0]);

                fetch('{{ route("upload.preview") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        previewData = data;
                        document.getElementById('previewContent').innerHTML = data.html;
                        initializeCheckboxes();
                    } else {
                        alert(data.message || 'Error loading preview');
                        modal.classList.add('hidden');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat memuat preview');
                    modal.classList.add('hidden');
                });
            });

            // Initialize checkboxes
            function initializeCheckboxes() {
                const selectAll = document.getElementById('selectAllColumns');
                if (selectAll) {
                    selectAll.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('.column-checkbox');
                        checkboxes.forEach(cb => cb.checked = this.checked);
                    });
                }
            }

            // Confirm upload
            confirmUpload.addEventListener('click', function() {
                const selectedColumns = {};
                const checkboxes = document.querySelectorAll('.column-checkbox:checked');
                
                if (checkboxes.length === 0) {
                    alert('Pilih minimal satu kolom untuk diimport');
                    return;
                }

                checkboxes.forEach(cb => {
                    const excelCol = cb.dataset.excelCol;
                    const mappingSelect = document.getElementById('mapping_' + excelCol);
                    if (mappingSelect) {
                        selectedColumns[excelCol] = mappingSelect.value;
                    }
                });

                const formData = new FormData(form);
                formData.append('selected_columns', JSON.stringify(selectedColumns));
                
                confirmUpload.disabled = true;
                confirmUpload.innerHTML = '<svg class="animate-spin h-4 w-4 mr-2 inline" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Uploading...';

                fetch('{{ route("upload.process") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(data.message);
                        window.location.reload();
                    } else {
                        alert(data.message || 'Error uploading data');
                        confirmUpload.disabled = false;
                        confirmUpload.innerHTML = '<svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>Upload Data';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat upload');
                    confirmUpload.disabled = false;
                    confirmUpload.innerHTML = '<svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>Upload Data';
                });
            });

            // Close modal handlers
            closeModal.addEventListener('click', () => modal.classList.add('hidden'));
            closeModalX.addEventListener('click', () => modal.classList.add('hidden'));
        });
    </script>
    @endpush
</x-app-layout>