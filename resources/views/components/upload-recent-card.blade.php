<div class="bg-white shadow-lg rounded-2xl border border-gray-200 p-5" x-data="uploadRecentCard()" x-init="init()">
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-xl bg-[#e8f1fb] flex items-center justify-center text-[#0057b7]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 1.343-3 3v4h6v-4c0-1.657-1.343-3-3-3z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 16v2"/>
                </svg>
            </div>
            <div>
                <p class="text-sm text-gray-600">Recent Uploads</p>
                <p class="text-lg font-semibold text-gray-900">Progress Terbaru</p>
            </div>
        </div>
        <div class="flex items-center space-x-3">
            <button @click="clearHistory()" class="text-xs font-semibold text-red-600 hover:underline">Hapus selesai / stuck</button>
            <button @click="fetchData()" class="text-xs font-semibold text-[#0057b7] hover:underline">Refresh</button>
        </div>
    </div>

    <template x-if="runs.length === 0">
        <p class="text-sm text-gray-500">Belum ada upload terbaru.</p>
    </template>

    <div class="space-y-3" x-show="runs.length > 0">
        <template x-for="run in runs" :key="run.id">
            <div class="p-3 rounded-xl border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-900" x-text="run.format || '-'"></p>
                        <p class="text-xs text-gray-500">Mode: <span x-text="run.upload_mode"></span> | Period: <span x-text="run.period_date || '-'"></span></p>
                    </div>
                    <span class="px-2 py-1 rounded-lg text-xs font-semibold" :class="statusClass(run.status)" x-text="run.status"></span>
                </div>
                <div class="mt-2">
                    <div class="h-2 rounded-full bg-gray-100 overflow-hidden">
                        <div class="h-2 bg-[#0057b7]" :style="`width: ${run.progress_percent || 0}%`"></div>
                    </div>
                    <div class="flex justify-between text-xs text-gray-500 mt-1">
                        <span x-text="(run.progress_percent || 0) + '%'"></span>
                        <span x-text="run.created_at"></span>
                    </div>
                </div>
                <template x-if="run.status === 'failed' && run.message">
                    <p class="mt-2 text-xs text-red-600 line-clamp-3" x-text="run.message"></p>
                </template>
            </div>
        </template>
    </div>
</div>

<script>
    function uploadRecentCard() {
        return {
            runs: [],
            init() {
                this.fetchData();
                setInterval(() => this.fetchData(), 4000);
            },
            fetchData() {
                fetch('{{ route('uploads.recent') }}', {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                    .then(r => r.json())
                    .then(json => {
                        this.runs = json.data || [];
                    })
                    .catch(() => {});
            },
            clearHistory() {
                fetch('{{ route('uploads.clear') }}', {
                    method: 'DELETE',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                }).then(() => this.fetchData());
            },
            statusClass(status) {
                switch (status) {
                    case 'success':
                        return 'bg-green-100 text-green-800';
                    case 'processing':
                    case 'pending':
                        return 'bg-blue-100 text-blue-800';
                    case 'failed':
                        return 'bg-red-100 text-red-800';
                    default:
                        return 'bg-gray-100 text-gray-600';
                }
            }
        }
    }
</script>
