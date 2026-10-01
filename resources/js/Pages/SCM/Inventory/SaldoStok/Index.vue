<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Produk {
    id: number;
    nama_produk: string;
    kode_produk: string;
    item_type?: string;
    safety_stock?: number;
    min_stock?: number;
    max_stock?: number;
    odoo_uom?: string;
    uom?: { code?: string } | null;
}
interface Stock {
    id: number;
    quantity: number;
    batch_number?: string | null;
    location: string;
    expired_date?: string | null;
    produk?: Produk;
}

const props = defineProps<{
    stocks: {
        data: Stock[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    item_type?: string;
    counts?: {
        all: number;
        fg: number;
        rm: number;
        pm: number;
        wip: number;
    };
    produks: Produk[];
    lossWidget: {
        window_days: number;
        scrap_qty: number;
        usage_variance: number;
        total_standard: number;
        loss_ratio: number;
        revenue: number;
    };
    uoms: Array<{ id: number; code: string; name: string }>;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.search);
const currentType = ref(props.item_type || 'all');
const showAdjust = ref(false);
const isWarehouse = computed(() =>
    ['warehouse_admin', 'superadmin', 'admin'].includes(
        (usePage().props as any).auth?.user?.role ?? '',
    ),
);

const adjustForm = useForm({
    produk_id: '',
    batch_number: '',
    location: 'GUDANG-UTAMA',
    quantity: '',
    expired_date: '',
    notes: '',
});

function stockState(s: Stock): { label: string; cls: string } {
    const q = Number(s.quantity);
    const min = Number(s.produk?.min_stock ?? 0);
    const safety = Number(s.produk?.safety_stock ?? 0);
    if (q <= 0) return { label: 'Habis', cls: 'bg-red-100 text-red-800 dark:bg-red-950/40 dark:text-red-300' };
    if (min > 0 && q < min)
        return { label: 'Di bawah min', cls: 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300' };
    if (safety > 0 && q < safety)
        return {
            label: 'Di bawah safety',
            cls: 'bg-orange-100 text-orange-800 dark:bg-orange-950/40 dark:text-orange-300',
        };
    return { label: 'Aman', cls: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300' };
}

function itemTypeBadge(type?: string): { label: string; cls: string } {
    const t = (type || 'fg').toLowerCase();
    switch (t) {
        case 'rm':
            return { label: 'Raw Material (RM)', cls: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800' };
        case 'pm':
            return { label: 'Packaging (PM)', cls: 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800' };
        case 'wip':
            return { label: 'WIP', cls: 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800' };
        case 'fg':
        default:
            return { label: 'Finished Good (FG)', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' };
    }
}

function submitAdjust() {
    adjustForm.post(route('scm.saldo-stok.adjust'), {
        onSuccess: () => {
            showAdjust.value = false;
            adjustForm.reset();
        },
    });
}

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

function filterByType(type: string) {
    currentType.value = type;
    goToPage(1);
}

function goToPage(page: number) {
    router.get(
        route('scm.saldo-stok.index'),
        {
            page,
            search: search.value,
            item_type: currentType.value !== 'all' ? currentType.value : undefined,
        },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Saldo Stok" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Saldo Stok (Stock Ledger)
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Pondasi Stock Opname & Delivery — sinkron dari Odoo / Material Usage / Scrap
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="syncing"
                        @click="syncStocks"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
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
                        <span>{{ syncing ? 'Menyinkronkan...' : 'Sync Stok Odoo' }}</span>
                    </button>
                    <PrimaryButton
                        v-if="isWarehouse"
                        class="py-2 text-xs"
                        @click="showAdjust = true"
                    >
                        + Penyesuaian Stok
                    </PrimaryButton>
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

            <!-- Loss Inventory Widget -->
            <div
                class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <h3 class="mb-3 text-sm font-semibold uppercase text-gray-500">
                    Loss Inventory ({{ lossWidget.window_days }} hari)
                </h3>
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                    <div>
                        <div class="text-xs text-gray-500">Scrap Qty</div>
                        <div
                            class="text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ lossWidget.scrap_qty }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Usage Variance</div>
                        <div
                            class="text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ lossWidget.usage_variance }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Total Standard</div>
                        <div
                            class="text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ lossWidget.total_standard }}
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Loss Ratio</div>
                        <div
                            class="text-lg font-bold"
                            :class="
                                lossWidget.loss_ratio > 2
                                    ? 'text-red-600'
                                    : 'text-emerald-600'
                            "
                        >
                            {{ lossWidget.loss_ratio }}%
                        </div>
                    </div>
                    <div>
                        <div class="text-xs text-gray-500">Revenue</div>
                        <div class="text-lg font-bold text-gray-400">
                            {{
                                lossWidget.revenue === 0
                                    ? 'qty only'
                                    : lossWidget.revenue
                            }}
                        </div>
                        <div class="text-[10px] text-gray-400">
                            belum ada SO revenue
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                <button
                    type="button"
                    @click="filterByType('all')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        currentType === 'all'
                            ? 'bg-indigo-600 text-white shadow-sm dark:bg-indigo-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    Semua Item
                    <span
                        v-if="counts?.all !== undefined"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="currentType === 'all' ? 'bg-indigo-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ counts.all }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="filterByType('fg')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        currentType === 'fg'
                            ? 'bg-emerald-600 text-white shadow-sm dark:bg-emerald-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                    Finished Goods (FG)
                    <span
                        v-if="counts?.fg !== undefined"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="currentType === 'fg' ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ counts.fg }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="filterByType('rm')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        currentType === 'rm'
                            ? 'bg-amber-600 text-white shadow-sm dark:bg-amber-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span>
                    Raw Material (RM / Bahan Baku)
                    <span
                        v-if="counts?.rm !== undefined"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="currentType === 'rm' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ counts.rm }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="filterByType('pm')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        currentType === 'pm'
                            ? 'bg-purple-600 text-white shadow-sm dark:bg-purple-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-purple-400"></span>
                    Packaging Material (PM / Kemas)
                    <span
                        v-if="counts?.pm !== undefined"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="currentType === 'pm' ? 'bg-purple-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ counts.pm }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="filterByType('wip')"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        currentType === 'wip'
                            ? 'bg-sky-600 text-white shadow-sm dark:bg-sky-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-sky-400"></span>
                    WIP
                    <span
                        v-if="counts?.wip !== undefined"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="currentType === 'wip' ? 'bg-sky-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ counts.wip }}
                    </span>
                </button>
            </div>

            <div class="flex items-center justify-between gap-3">
                <SearchInput
                    v-model="search"
                    placeholder="Cari produk / batch..."
                    class="max-w-md"
                    @search="goToPage(1)"
                />
                <span class="text-xs text-gray-500"
                    >Total: {{ stocks.total }} item</span
                >
            </div>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Produk / Material
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Kategori
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Batch
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Lokasi
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Qty Saldo
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Min / Safety
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Status
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="s in stocks.data" :key="s.id">
                            <td class="px-3 py-2">
                                <div
                                    class="font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ s.produk?.nama_produk ?? '-' }}
                                </div>
                                <div class="text-[11px] text-gray-500 font-mono">
                                    {{ s.produk?.kode_produk ?? '' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <span
                                    :class="itemTypeBadge(s.produk?.item_type).cls"
                                    class="inline-block rounded-md border px-2 py-0.5 text-[10px] font-semibold"
                                >
                                    {{ itemTypeBadge(s.produk?.item_type).label }}
                                </span>
                            </td>
                            <td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-300">
                                {{ s.batch_number ?? '-' }}
                            </td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ s.location }}</td>
                            <td
                                class="px-3 py-2 text-right font-mono font-bold"
                                :class="
                                    Number(s.quantity) <= 0
                                        ? 'text-red-600'
                                        : 'text-gray-900 dark:text-gray-100'
                                "
                            >
                                {{ Number(s.quantity).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 3 }) }}
                                <span class="text-[10px] font-normal text-gray-500 ml-0.5">{{ s.produk?.uom?.code ?? s.produk?.odoo_uom ?? '' }}</span>
                            </td>
                            <td class="px-3 py-2 text-gray-500">
                                {{ s.produk?.min_stock ?? 0 }} /
                                {{ s.produk?.safety_stock ?? 0 }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    :class="stockState(s).cls"
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    >{{ stockState(s).label }}</span
                                >
                            </td>
                        </tr>
                        <tr v-if="stocks.data.length === 0">
                            <td
                                colspan="7"
                                class="px-3 py-8 text-center text-gray-400"
                            >
                                Belum ada data stok pada kategori ini
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="stocks.current_page"
                        :last-page="stocks.last_page"
                        :total="stocks.total"
                        :per-page="stocks.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>

        <Modal :show="showAdjust" @close="showAdjust = false">
            <form class="space-y-3 p-6" @submit.prevent="submitAdjust">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Penyesuaian Stok
                </h3>
                <div>
                    <InputLabel value="Produk" />
                    <select
                        v-model="adjustForm.produk_id"
                        class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        required
                    >
                        <option value="" disabled>Pilih produk</option>
                        <option v-for="m in produks" :key="m.id" :value="m.id">
                            {{ m.nama_produk }}
                        </option>
                    </select>
                    <InputError
                        :message="adjustForm.errors.produk_id"
                        class="mt-1"
                    />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <InputLabel value="Batch (opsional)" />
                        <TextInput
                            v-model="adjustForm.batch_number"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel value="Lokasi" />
                        <TextInput
                            v-model="adjustForm.location"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel value="Qty (+ / −)" />
                        <TextInput
                            v-model="adjustForm.quantity"
                            type="number"
                            step="0.001"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="adjustForm.errors.quantity"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Expired (opsional)" />
                        <TextInput
                            v-model="adjustForm.expired_date"
                            type="date"
                            class="mt-1 block w-full"
                        />
                    </div>
                </div>
                <div>
                    <InputLabel value="Notes" />
                    <TextInput
                        v-model="adjustForm.notes"
                        class="mt-1 block w-full"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showAdjust = false"
                        >Batal</SecondaryButton
                    >
                    <PrimaryButton :disabled="adjustForm.processing"
                        >Simpan</PrimaryButton
                    >
                </div>
            </form>
        </Modal>
    </AuthenticatedLayout>
</template>
