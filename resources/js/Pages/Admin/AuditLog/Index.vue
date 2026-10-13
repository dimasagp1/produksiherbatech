<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface AuditLogItem {
    id: number;
    auditable_type: string;
    auditable_id: string | number;
    event: 'created' | 'updated' | 'deleted' | string;
    old_values?: Record<string, any> | null;
    new_values?: Record<string, any> | null;
    user_id?: number | null;
    reason?: string | null;
    ip?: string | null;
    created_at?: string | null;
}

interface UserOption {
    id: number;
    name: string;
}

const props = defineProps<{
    logs: {
        data: AuditLogItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    users: UserOption[];
    usersMap: Record<number, string>;
    filters: {
        search: string;
        event: string;
        user_id: string;
        auditable_type: string;
    };
}>();

const search = ref(props.filters.search || '');
const event = ref(props.filters.event || '');
const userId = ref(props.filters.user_id || '');
const expandedLogId = ref<number | null>(null);

function applyFilter() {
    router.get(
        route('admin.audit-log.index'),
        {
            search: search.value || undefined,
            event: event.value || undefined,
            user_id: userId.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function resetFilter() {
    search.value = '';
    event.value = '';
    userId.value = '';
    router.get(route('admin.audit-log.index'));
}

function toggleExpand(id: number) {
    expandedLogId.value = expandedLogId.value === id ? null : id;
}

function formatModelName(fullClass: string): string {
    const parts = fullClass.split('\\');
    return parts[parts.length - 1] ?? fullClass;
}

function eventBadgeClass(ev: string): string {
    switch (ev) {
        case 'created':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300';
        case 'updated':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300';
        case 'deleted':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-300';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
    }
}
</script>

<template>
    <Head title="Audit Trail Log" />

    <AuthenticatedLayout>
        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">
                <!-- Header -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">
                            Audit Trail Log
                        </h1>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Jejak riwayat perubahan data sistem (append-only) untuk kepatuhan & audit manufaktur.
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-300">
                            Total Record: {{ logs.total }}
                        </span>
                    </div>
                </div>

                <!-- Filter Panel -->
                <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400">Pencarian</label>
                            <input
                                v-model="search"
                                @keyup.enter="applyFilter"
                                type="text"
                                placeholder="Cari entitas, ID, alasan..."
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400">Jenis Aksi (Event)</label>
                            <select
                                v-model="event"
                                @change="applyFilter"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="">Semua Aksi</option>
                                <option value="created">Created (Dibuat)</option>
                                <option value="updated">Updated (Diubah)</option>
                                <option value="deleted">Deleted (Dihapus)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 dark:text-gray-400">Pelaku (User)</label>
                            <select
                                v-model="userId"
                                @change="applyFilter"
                                class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            >
                                <option value="">Semua User</option>
                                <option v-for="u in users" :key="u.id" :value="u.id">
                                    {{ u.name }}
                                </option>
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button
                                @click="applyFilter"
                                type="button"
                                class="inline-flex w-full items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-500 focus:outline-none"
                            >
                                Filter
                            </button>
                            <button
                                @click="resetFilter"
                                type="button"
                                class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                            >
                                Reset
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Table -->
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Waktu</th>
                                    <th class="px-4 py-3">Aksi</th>
                                    <th class="px-4 py-3">Entitas</th>
                                    <th class="px-4 py-3">Pelaku</th>
                                    <th class="px-4 py-3">IP / Alasan</th>
                                    <th class="px-4 py-3 text-right">Detail Nilai</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                <template v-for="log in logs.data" :key="log.id">
                                    <tr class="hover:bg-gray-50/50 dark:hover:bg-gray-800/40">
                                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                            {{ log.created_at ?? '-' }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span :class="['inline-flex rounded-full px-2 py-0.5 text-xs font-semibold', eventBadgeClass(log.event)]">
                                                {{ log.event.toUpperCase() }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                            <span class="text-indigo-600 dark:text-indigo-400 font-bold">
                                                {{ formatModelName(log.auditable_type) }}
                                            </span>
                                            <span class="text-gray-400 text-xs ml-1 font-mono">#{{ log.auditable_id }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                            {{ log.user_id ? (usersMap[log.user_id] ?? `User #${log.user_id}`) : 'System' }}
                                        </td>
                                        <td class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400">
                                            <div>{{ log.ip ?? '-' }}</div>
                                            <div v-if="log.reason" class="text-gray-400 italic">{{ log.reason }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            <button
                                                @click="toggleExpand(log.id)"
                                                type="button"
                                                class="rounded-md border border-gray-300 px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800"
                                            >
                                                {{ expandedLogId === log.id ? 'Tutup' : 'Lihat Diff' }}
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Expanded Diff Row -->
                                    <tr v-if="expandedLogId === log.id" class="bg-gray-50/80 dark:bg-gray-800/30">
                                        <td colspan="6" class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-mono">
                                                <div class="rounded-lg border border-red-200 bg-red-50/50 p-3 dark:border-red-900/30 dark:bg-red-950/20">
                                                    <div class="font-bold text-red-700 dark:text-red-400 mb-1">
                                                        - Nilai Sebelum (Old Values)
                                                    </div>
                                                    <pre class="overflow-x-auto whitespace-pre-wrap text-red-900 dark:text-red-300">{{ log.old_values ? JSON.stringify(log.old_values, null, 2) : 'Tidak ada (Baru dibuat)' }}</pre>
                                                </div>
                                                <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 dark:border-emerald-900/30 dark:bg-emerald-950/20">
                                                    <div class="font-bold text-emerald-700 dark:text-emerald-400 mb-1">
                                                        + Nilai Sesudah (New Values)
                                                    </div>
                                                    <pre class="overflow-x-auto whitespace-pre-wrap text-emerald-900 dark:text-emerald-300">{{ log.new_values ? JSON.stringify(log.new_values, null, 2) : 'Tidak ada (Dihapus)' }}</pre>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <tr v-if="logs.data.length === 0">
                                    <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Belum ada riwayat audit log yang tercatat.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div v-if="logs.last_page > 1" class="flex items-center justify-between border-t border-gray-200 px-4 py-3 dark:border-gray-800">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Halaman {{ logs.current_page }} dari {{ logs.last_page }}
                        </div>
                        <div class="flex gap-1">
                            <button
                                v-for="(link, i) in logs.links"
                                :key="i"
                                :disabled="!link.url"
                                @click="link.url && router.visit(link.url)"
                                v-html="link.label"
                                :class="[
                                    'rounded px-2.5 py-1 text-xs font-medium',
                                    link.active
                                        ? 'bg-indigo-600 text-white'
                                        : 'border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800',
                                    !link.url && 'opacity-50 cursor-not-allowed',
                                ]"
                            ></button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
