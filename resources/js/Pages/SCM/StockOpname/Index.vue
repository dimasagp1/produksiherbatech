<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Opname {
    id: number;
    opname_number: string;
    status: string;
    location: string;
    initiated_at?: string | null;
    ira_persen?: number | null;
    discrepancy_value_rate?: number | null;
    initiator?: { name: string } | null;
    approver?: { name: string } | null;
}

const props = defineProps<{
    opnames: {
        data: Opname[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    status: string | null;
    iraTarget: number;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const status = ref(props.status ?? '');
const isWarehouse = computed(() =>
    ['warehouse_admin', 'superadmin', 'admin'].includes(
        (usePage().props as any).auth?.user?.role ?? '',
    ),
);

const initiateForm = useForm({
    location: 'GUDANG-UTAMA',
    item_type: 'all',
    notes: '',
});

const syncing = ref(false);

function syncStocks() {
    syncing.value = true;
    router.post(
        route('scm.saldo-stok.sync-odoo'),
        {},
        {
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

const statusColor: Record<string, string> = {
    initiated: 'bg-gray-100 text-gray-700',
    counted: 'bg-sky-100 text-sky-800',
    recalculated: 'bg-indigo-100 text-indigo-800',
    posted: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
};

function initiate() {
    initiateForm.post(route('scm.stock-opname.store'));
}

function applyFilter() {
    router.get(
        route('scm.stock-opname.index'),
        { status: status.value || undefined },
        { preserveState: true },
    );
}

function goToPage(page: number) {
    router.get(
        route('scm.stock-opname.index'),
        { page, status: status.value || undefined },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Stock Opname" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Stock Opname & Validasi Fisik IC
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Validasi data buku Odoo vs hitungan fisik lapangan (FG, Raw Material, Packaging Material) · Target IRA ≥ {{ iraTarget }}%
                    </p>
                </div>
                <div v-if="isWarehouse" class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :disabled="syncing"
                        @click="syncStocks"
                        title="Tarik saldo stok terbaru dari Odoo sebelum opname"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg
                            class="h-3.5 w-3.5 text-indigo-600 dark:text-indigo-400"
                            :class="{ 'animate-spin': syncing }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        <span>{{ syncing ? 'Syncing...' : 'Sync Odoo' }}</span>
                    </button>
                    <select
                        v-model="initiateForm.item_type"
                        class="rounded-md border-gray-300 py-1.5 text-xs font-medium dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="all">Semua Kategori (FG, RM, PM)</option>
                        <option value="fg">Finished Goods (FG)</option>
                        <option value="rm">Raw Material (RM / Bahan Baku)</option>
                        <option value="pm">Packaging Material (PM / Kemas)</option>
                        <option value="wip">WIP</option>
                    </select>
                    <select
                        v-model="initiateForm.location"
                        class="rounded-md border-gray-300 py-1.5 text-xs font-medium dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    >
                        <option value="GUDANG-UTAMA">GUDANG-UTAMA</option>
                    </select>
                    <PrimaryButton
                        class="py-1.5 text-xs"
                        :disabled="initiateForm.processing"
                        @click="initiate"
                        >+ Buat Snapshot Opname</PrimaryButton
                    >
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div
                v-if="flash.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ flash.success }}
            </div>

            <select
                v-model="status"
                class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                @change="applyFilter"
            >
                <option value="">Semua status</option>
                <option
                    v-for="s in [
                        'initiated',
                        'counted',
                        'recalculated',
                        'posted',
                        'approved',
                        'cancelled',
                    ]"
                    :key="s"
                    :value="s"
                >
                    {{ s }}
                </option>
            </select>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                No. Opname
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Lokasi
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Initiated
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Status
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                IRA %
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                DVR %
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="o in opnames.data" :key="o.id">
                            <td
                                class="px-3 py-2 font-mono font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ o.opname_number }}
                            </td>
                            <td class="px-3 py-2">{{ o.location }}</td>
                            <td class="px-3 py-2">
                                {{
                                    o.initiated_at
                                        ? new Date(
                                              o.initiated_at,
                                          ).toLocaleDateString('id-ID')
                                        : '-'
                                }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    :class="
                                        statusColor[o.status] ?? 'bg-gray-100'
                                    "
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    >{{ o.status }}</span
                                >
                            </td>
                            <td
                                class="px-3 py-2 text-right font-bold"
                                :class="
                                    (o.ira_persen ?? 0) >= iraTarget
                                        ? 'text-emerald-600'
                                        : 'text-red-600'
                                "
                            >
                                {{ o.ira_persen ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ o.discrepancy_value_rate ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <Link
                                    :href="route('scm.stock-opname.show', o.id)"
                                    class="font-semibold text-indigo-600 hover:underline"
                                    >Buka</Link
                                >
                            </td>
                        </tr>
                        <tr v-if="opnames.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-8 text-center text-gray-400"
                            >
                                Belum ada opname
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="opnames.current_page"
                        :last-page="opnames.last_page"
                        :total="opnames.total"
                        :per-page="opnames.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
