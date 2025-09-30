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
                @if ($errors->any())
                    <div class="p-4 text-sm text-red-700 bg-red-100 rounded-lg" role="alert">
                        <span class="font-medium">Error!</span>
                        <ul class="mt-2 list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
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
                        
                        {{-- Form Upload dengan Preview --}}
                        <form id="uploadForm" method="POST" enctype="multipart/form-data" class="space-y-4">
                            @csrf
                            <div>
                                <x-input-label for="mapping_id" :value="__('Pilih Format Laporan')" />
                                <select name="mapping_id" id="mapping_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" required>
                                    <option value="">-- Harap Pilih --</option>
                                    @foreach($mappings as $mapping)
                                        <option value="{{ $mapping->id }}">{{ $mapping->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="data_file" :value="__('Pilih File Excel')" />
                                <input type="file" name="data_file" id="data_file" accept=".xlsx,.xls" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 focus:outline-none" required>
                            </div>
                            <div>
                                <x-primary-button type="button" id="previewButton">
                                    {{ __('Preview & Upload') }}
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
                                <a href="{{ route('mapping.register.form') }}" class="text-sm">
                                    <x-secondary-button>
                                        + Buat Format Baru
                                    </x-secondary-button>
                                </a>
                            @endcan
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            Lihat daftar format yang sudah terdaftar di sistem.
                        </p>
                        <div class="border rounded-lg max-h-60 overflow-y-auto">
                            <ul class="divide-y divide-gray-200">
                                @forelse ($mappings as $mapping)
                                    <li class="px-4 py-3">
                                        <p class="text-sm font-medium text-gray-900 truncate">{{ $mapping->description }}</p>
                                        <p class="text-xs text-gray-500">Tabel: {{ $mapping->table_name ?? 'N/A' }}</p>
                                        <p class="text-xs text-gray-500">Kolom: {{ $mapping->columns->pluck('table_column_name')->implode(', ') }}</p>
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

    {{-- Modal Preview --}}
    <div id="previewModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-6xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <h3 class="text-lg font-medium text-gray-900 mb-4">Preview Data & Mapping Kolom</h3>
                
                <div id="previewContent" class="mb-4">
                    {{-- Content will be loaded via AJAX --}}
                </div>

                <div class="flex justify-end space-x-2">
                    <x-secondary-button type="button" id="closeModal">
                        Batal
                    </x-secondary-button>
                    <x-primary-button type="button" id="confirmUpload">
                        Upload Data
                    </x-primary-button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const previewButton = document.getElementById('previewButton');
            const confirmUpload = document.getElementById('confirmUpload');
            const closeModal = document.getElementById('closeModal');
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

                // Show loading
                document.getElementById('previewContent').innerHTML = '<div class="text-center py-4"><p>Loading preview...</p></div>';
                modal.classList.remove('hidden');

                // Create FormData
                const formData = new FormData();
                formData.append('_token', '{{ csrf_token() }}');
                formData.append('mapping_id', mappingId);
                formData.append('data_file', fileInput.files[0]);

                // Fetch preview
                fetch('{{ route("upload.preview") }}', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('previewContent').innerHTML = data.html;
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

            // Confirm upload
            confirmUpload.addEventListener('click', function() {
                const formData = new FormData(form);
                
                // Show loading
                confirmUpload.disabled = true;
                confirmUpload.textContent = 'Uploading...';

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
                        confirmUpload.textContent = 'Upload Data';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Terjadi kesalahan saat upload');
                    confirmUpload.disabled = false;
                    confirmUpload.textContent = 'Upload Data';
                });
            });

            // Close modal
            closeModal.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
        });
    </script>
    @endpush
</x-app-layout>