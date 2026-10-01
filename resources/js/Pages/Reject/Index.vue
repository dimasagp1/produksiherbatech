<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate } from '@/utils/date';
import { Head, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface UserCreator {
    id: number;
    name: string;
}

interface RejectDetailItem {
    id: number;
    laporan_harian_id: number;
    jenis_reject: 'sublayer' | 'ga' | 'process';
    jumlah: number;
    keterangan: string | null;
    odoo_scrap_id: number | null;
    odoo_synced_at: string | null;
    created_at: string;
    created_by: number;
    creator?: UserCreator;
}

interface Produk {
    id: number;
    nama_produk: string;
    kode_produk?: string;
}

interface LaporanItem {
    id: number;
    tanggal: string;
    batch_number: string;
    proses: string;
    output_fisik: number | null;
    capacity_fisik: number | null;
    available_qty: number;
    total_reject: number;
    sisa_qty: number;
    produk: Produk;
    line?: { nama_line: string };
    mesin?: { nama_mesin: string };
    reject_details?: RejectDetailItem[];
}

const props = defineProps<{
    productions: {
        data: LaporanItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    filter?: 'all' | 'has_reject' | 'odoo';
}>();

const page = usePage() as any;
const flashSuccess = computed(
    () => page.props.flash?.success ?? page.props.success ?? null,
);
const isManager = computed(
    () => (page.props.auth as any)?.user?.role === 'manager',
);
const currentUserId = computed(() => (page.props.auth as any)?.user?.id);
const currentUserRole = computed(() => (page.props.auth as any)?.user?.role);

// Tabs: Only 2 main views
const activeTab = ref<'local' | 'odoo'>('local');
const search = ref(props.search ?? '');
const currentFilter = ref<'all' | 'has_reject' | 'odoo'>(props.filter ?? 'all');
const selected = ref<LaporanItem | null>(null);
const showModal = ref(false);
const showConfirm = ref(false);
const isSubmitting = ref(false);

// Accordion / Dropdown state for Local Production Batches
const expandedBatches = ref<Record<number, boolean>>({});
function toggleBatchExpand(id: number) {
    expandedBatches.value[id] = !expandedBatches.value[id];
}

// Accordion / Dropdown state for Odoo Scraps
const expandedOdooScraps = ref<Record<number, boolean>>({});
function toggleOdooScrapExpand(id: number) {
    expandedOdooScraps.value[id] = !expandedOdooScraps.value[id];
}

// Odoo Live Scraps state
const odooScraps = ref<any[]>([]);
const isLoadingOdooScraps = ref(false);
const odooScrapError = ref<string | null>(null);
const odooSearch = ref('');

async function fetchOdooScraps() {
    isLoadingOdooScraps.value = true;
    odooScrapError.value = null;
    try {
        const res = await axios.get(route('admin.odoo.scraps'));
        if (res.data.success) {
            odooScraps.value = res.data.scraps || [];
        } else {
            odooScrapError.value =
                res.data.message || 'Gagal memuat data scrap dari Odoo.';
        }
    } catch (err: any) {
        odooScrapError.value =
            err.response?.data?.message ||
            err.message ||
            'Gagal menghubungi server Odoo.';
    } finally {
        isLoadingOdooScraps.value = false;
    }
}

watch(activeTab, (tab) => {
    if (tab === 'odoo' && odooScraps.value.length === 0) {
        fetchOdooScraps();
    }
});

const odooCurrentPage = ref(1);
const odooPerPage = ref(15);

const filteredOdooScraps = computed(() => {
    if (!odooSearch.value.trim()) return odooScraps.value;
    const q = odooSearch.value.toLowerCase().trim();
    return odooScraps.value.filter(
        (item) =>
            (item.name && item.name.toLowerCase().includes(q)) ||
            (item.product_name &&
                item.product_name.toLowerCase().includes(q)) ||
            (item.origin && item.origin.toLowerCase().includes(q)) ||
            (item.batch_number && item.batch_number.toLowerCase().includes(q)),
    );
});

watch(odooSearch, () => {
    odooCurrentPage.value = 1;
});

const odooLastPage = computed(() =>
    Math.max(1, Math.ceil(filteredOdooScraps.value.length / odooPerPage.value)),
);

const paginatedOdooScraps = computed(() => {
    const start = (odooCurrentPage.value - 1) * odooPerPage.value;
    return filteredOdooScraps.value.slice(start, start + odooPerPage.value);
});

// Odoo MO Reject Sync
const syncingRejects = ref(false);
function syncMoRejects() {
    if (isManager.value || syncingRejects.value) return;
    syncingRejects.value = true;
    router.post(
        route('reject.sync-odoo'),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingRejects.value = false;
            },
        },
    );
}

// Single reject delete
const rejectToDelete = ref<RejectDetailItem | null>(null);
const showDeleteConfirm = ref(false);
const isDeleting = ref(false);

function openDeleteModal(reject: RejectDetailItem) {
    if (isManager.value) return;
    rejectToDelete.value = reject;
    showDeleteConfirm.value = true;
}

function confirmDeleteReject() {
    if (!rejectToDelete.value) return;
    isDeleting.value = true;
    router.delete(route('reject.destroy', rejectToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteConfirm.value = false;
            rejectToDelete.value = null;
        },
        onFinish: () => {
            isDeleting.value = false;
        },
    });
}

function canDeleteReject(reject: RejectDetailItem): boolean {
    if (isManager.value) return false;
    if (
        currentUserRole.value === 'superadmin' ||
        currentUserRole.value === 'admin' ||
        currentUserRole.value === 'spv'
    ) {
        return true;
    }
    return reject.created_by === currentUserId.value;
}

// Form state
const form = ref({
    alasan_reject: '',
    qty_reject: '' as string | number,
    jenis_reject: '' as '' | 'sublayer' | 'ga' | 'process',
});

const errors = ref<Record<string, string>>({});

const jenisOptions = [
    { value: 'sublayer', label: 'Reject Sublayer' },
    { value: 'ga', label: 'Reject GA' },
    { value: 'process', label: 'Proses' },
];

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
    packaging: 'Packaging',
};

function getJenisBadge(jenis: string) {
    switch (jenis) {
        case 'sublayer':
            return {
                label: 'Reject Sublayer',
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
            };
        case 'ga':
            return {
                label: 'Reject GA',
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
            };
        case 'process':
            return {
                label: 'Reject Proses',
                class: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
            };
        default:
            return {
                label: jenis,
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
            };
    }
}

function openReject(item: LaporanItem) {
    if (isManager.value) return;
    selected.value = item;
    form.value = { alasan_reject: '', qty_reject: '', jenis_reject: '' };
    errors.value = {};
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
    showConfirm.value = false;
    errors.value = {};
}

function validate(): boolean {
    const e: Record<string, string> = {};
    if (
        !form.value.alasan_reject ||
        form.value.alasan_reject.trim().length < 3
    ) {
        e.alasan_reject = 'Alasan reject wajib diisi.';
    }
    if (form.value.qty_reject === '' || form.value.qty_reject === null) {
        e.qty_reject = 'Qty reject wajib diisi.';
    } else {
        const n = Number(form.value.qty_reject);
        if (!Number.isFinite(n) || !Number.isInteger(n))
            e.qty_reject = 'Qty reject harus berupa angka bulat.';
        else if (n <= 0) e.qty_reject = 'Qty reject harus lebih besar dari 0.';
        else if (selected.value && n > selected.value.sisa_qty)
            e.qty_reject = `Qty reject tidak boleh melebihi qty produk yang tersedia. Sisa: ${selected.value.sisa_qty}`;
    }
    if (!form.value.jenis_reject)
        e.jenis_reject = 'Jenis reject wajib dipilih.';
    errors.value = e;
    return Object.keys(e).length === 0;
}

function onSubmit() {
    if (!validate()) return;
    showConfirm.value = true;
}

function confirmSubmit() {
    if (!selected.value) return;
    if (isSubmitting.value) return;
    isSubmitting.value = true;
    errors.value = {};
    router.post(
        route('reject.store'),
        {
            laporan_harian_id: selected.value.id,
            alasan_reject: form.value.alasan_reject.trim(),
            qty_reject: Number(form.value.qty_reject),
            jenis_reject: form.value.jenis_reject,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSubmitting.value = false;
                showConfirm.value = false;
                showModal.value = false;
                router.get(
                    route('reject.index'),
                    {
                        search: search.value,
                        page: props.productions.current_page,
                    },
                    { preserveState: false, replace: true },
                );
            },
            onError: (err) => {
                isSubmitting.value = false;
                errors.value = { ...errors.value, ...err } as Record<
                    string,
                    string
                >;
                showConfirm.value = false;
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function setFilter(newFilter: 'all' | 'has_reject' | 'odoo') {
    currentFilter.value = newFilter;
    router.get(
        route('reject.index'),
        { search: search.value, filter: newFilter, page: 1 },
        { preserveState: true },
    );
}

function goToPage(pageNum: number) {
    router.get(
        route('reject.index'),
        { page: pageNum, search: search.value, filter: currentFilter.value },
        { preserveState: true },
    );
}

function doSearch() {
    router.get(
        route('reject.index'),
        { search: search.value, filter: currentFilter.value, page: 1 },
        { preserveState: true },
    );
}

const jenisLabel = computed(() => {
    const v = form.value.jenis_reject;
    if (v === 'sublayer') return 'Reject Sublayer';
    if (v === 'ga') return 'Reject GA';
    if (v === 'process')
        return `Proses (${selected.value ? (prosesLabel[selected.value.proses] ?? selected.value.proses) : '-'})`;
    return '-';
});
</script>

<template>
    <Head title="Reject Produk" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Reject Produk
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Pencatatan reject produksi per nomor batch &
                        sinkronisasi Scrap Order ke Odoo ERP
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span
                        v-if="isManager"
                        class="rounded bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                        >Read-only</span
                    >
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{
                        isManager
                            ? 'Manager · read-only'
                            : 'Role Leader · sumber Laporan Harian'
                    }}</span>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <!-- flash -->
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200"
            >
                {{ flashSuccess }}
            </div>

            <!-- Tab Switcher (Responsive Scrollable Tabs) -->
            <div
                class="scrollbar-none mb-5 flex overflow-x-auto border-b border-gray-200 dark:border-gray-700"
            >
                <button
                    type="button"
                    @click="activeTab = 'local'"
                    class="flex flex-shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-xs font-semibold transition sm:text-sm"
                    :class="
                        activeTab === 'local'
                            ? 'border-red-600 text-red-600 dark:border-red-400 dark:text-red-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                        />
                    </svg>
                    <span>Input & Data Reject Produksi</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'odoo'"
                    class="flex flex-shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-xs font-semibold transition sm:text-sm"
                    :class="
                        activeTab === 'odoo'
                            ? 'border-red-600 text-red-600 dark:border-red-400 dark:text-red-400'
                            : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'
                    "
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                        />
                    </svg>
                    <span>Scrap Order Odoo ERP (Live)</span>
                    <span
                        v-if="odooScraps.length > 0"
                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                    >
                        {{ odooScraps.length }}
                    </span>
                </button>
            </div>

            <!-- ========================================== -->
            <!-- TAB 1: DATA REJECT PRODUKSI (LOKAL)        -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'local'">
                <div
                    class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center"
                    >
                        <SearchInput
                            v-model="search"
                            placeholder="Cari produk, no batch, proses..."
                            class="w-full max-w-md"
                            @search="doSearch"
                        />
                        <button
                            v-if="!isManager"
                            type="button"
                            :disabled="syncingRejects"
                            @click="syncMoRejects"
                            class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-red-200 bg-white px-3.5 py-2 text-xs font-semibold text-red-600 shadow-sm transition hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 active:scale-95 disabled:cursor-not-allowed disabled:opacity-60 dark:border-gray-700 dark:bg-gray-800 dark:text-red-400 dark:hover:bg-gray-700"
                        >
                            <svg
                                class="h-4 w-4 text-red-600 dark:text-red-400"
                                :class="{ 'animate-spin': syncingRejects }"
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
                            <span>{{
                                syncingRejects
                                    ? 'Sinkronisasi Reject...'
                                    : 'Sync Reject dari MO Odoo'
                            }}</span>
                        </button>
                    </div>
                    <p
                        class="text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs"
                    >
                        💡
                        <em
                            >Klik pada batch untuk membuka riwayat detail
                            reject.</em
                        >
                    </p>
                </div>

                <!-- Quick Filter Pills -->
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="setFilter('all')"
                        class="rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                        :class="
                            currentFilter === 'all'
                                ? 'bg-red-600 text-white dark:bg-red-500'
                                : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700'
                        "
                    >
                        Semua Batch
                    </button>
                    <button
                        type="button"
                        @click="setFilter('has_reject')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                        :class="
                            currentFilter === 'has_reject'
                                ? 'bg-red-600 text-white dark:bg-red-500'
                                : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700'
                        "
                    >
                        <span class="inline-block h-2 w-2 rounded-full bg-amber-500"></span>
                        Memiliki Reject
                    </button>
                    <button
                        type="button"
                        @click="setFilter('odoo')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold shadow-sm transition"
                        :class="
                            currentFilter === 'odoo'
                                ? 'bg-red-600 text-white dark:bg-red-500'
                                : 'bg-white text-gray-700 hover:bg-gray-100 border border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700 dark:hover:bg-gray-700'
                        "
                    >
                        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                        Tersinkron Odoo MO
                    </button>
                </div>

                <!-- DESKTOP TABLE VIEW (md and up) -->
                <div
                    class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-700/70">
                                <tr>
                                    <th
                                        class="w-8 px-2 py-2.5 text-center text-xs font-semibold uppercase text-gray-400"
                                    ></th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        No. Batch
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Nama Produk
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Process
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Qty Tersedia
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Total Reject
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Sisa
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                            >
                                <tr v-if="productions.data.length === 0">
                                    <td
                                        colspan="9"
                                        class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        Tidak ada data produksi
                                    </td>
                                </tr>
                                <template
                                    v-for="item in productions.data"
                                    :key="item.id"
                                >
                                    <tr
                                        @click="toggleBatchExpand(item.id)"
                                        class="cursor-pointer transition hover:bg-red-50/40 dark:hover:bg-gray-700/60"
                                        :class="{
                                            'bg-red-50/20 dark:bg-gray-700/30':
                                                expandedBatches[item.id],
                                        }"
                                    >
                                        <td
                                            class="px-2 py-2.5 text-center text-gray-400"
                                        >
                                            <svg
                                                class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                                :class="{
                                                    'rotate-90 text-red-600':
                                                        expandedBatches[
                                                            item.id
                                                        ],
                                                }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-gray-900 dark:text-gray-100"
                                        >
                                            <span
                                                class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-900 dark:bg-gray-700 dark:text-gray-100"
                                            >
                                                {{ item.batch_number }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{
                                                item.produk?.nama_produk ?? '-'
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-200"
                                        >
                                            {{
                                                prosesLabel[item.proses] ??
                                                item.proses
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-gray-600 dark:text-gray-300"
                                        >
                                            {{ formatDate(item.tanggal) }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-right font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{
                                                item.available_qty.toLocaleString(
                                                    'id-ID',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-right font-semibold"
                                            :class="
                                                item.total_reject > 0
                                                    ? 'text-amber-600 dark:text-amber-400'
                                                    : 'text-gray-400'
                                            "
                                        >
                                            {{
                                                item.total_reject.toLocaleString(
                                                    'id-ID',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-right font-semibold"
                                            :class="
                                                item.sisa_qty === 0
                                                    ? 'text-red-600'
                                                    : 'text-green-600'
                                            "
                                        >
                                            {{
                                                item.sisa_qty.toLocaleString(
                                                    'id-ID',
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-right"
                                            @click.stop
                                        >
                                            <span
                                                v-if="isManager"
                                                class="text-xs text-gray-400"
                                                >Read-only</span
                                            >
                                            <button
                                                v-else
                                                :disabled="item.sisa_qty === 0"
                                                @click="openReject(item)"
                                                class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold shadow-sm transition"
                                                :class="
                                                    item.sisa_qty === 0
                                                        ? 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-700'
                                                        : 'bg-red-600 text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:bg-red-500 dark:hover:bg-red-600'
                                                "
                                            >
                                                + Reject
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- DROPDOWN ACCORDION DETAIL BARIS PRODUKSI -->
                                    <tr
                                        v-if="expandedBatches[item.id]"
                                        class="bg-gray-50/90 dark:bg-gray-900/60"
                                    >
                                        <td colspan="9" class="p-3 sm:p-4">
                                            <div
                                                class="rounded-xl border border-red-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                            >
                                                <div
                                                    class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700"
                                                >
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <span
                                                            class="text-sm font-bold text-gray-900 dark:text-gray-100"
                                                        >
                                                            Detail Item Reject
                                                            Batch:
                                                            <span
                                                                class="font-mono text-red-600 dark:text-red-400"
                                                                >{{
                                                                    item.batch_number
                                                                }}</span
                                                            >
                                                        </span>
                                                        <span
                                                            class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                                                        >
                                                            Total Reject:
                                                            {{
                                                                item.total_reject
                                                            }}
                                                            pcs
                                                        </span>
                                                    </div>
                                                    <div
                                                        class="text-xs text-gray-500 dark:text-gray-400"
                                                    >
                                                        Produk:
                                                        <strong>{{
                                                            item.produk
                                                                ?.nama_produk
                                                        }}</strong>
                                                        | Sisa:
                                                        <strong
                                                            :class="
                                                                item.sisa_qty ===
                                                                0
                                                                    ? 'text-red-600'
                                                                    : 'text-green-600'
                                                            "
                                                            >{{
                                                                item.sisa_qty
                                                            }}
                                                            pcs</strong
                                                        >
                                                    </div>
                                                </div>

                                                <div
                                                    v-if="
                                                        !item.reject_details ||
                                                        item.reject_details
                                                            .length === 0
                                                    "
                                                    class="py-5 text-center text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    Belum ada item reject yang
                                                    diinput pada batch ini.
                                                    Tekan tombol
                                                    <strong>+ Reject</strong> di
                                                    atas untuk menambah data
                                                    reject.
                                                </div>

                                                <div
                                                    v-else
                                                    class="overflow-x-auto"
                                                >
                                                    <table
                                                        class="min-w-full divide-y divide-gray-200 text-xs dark:divide-gray-700"
                                                    >
                                                        <thead
                                                            class="bg-gray-50 dark:bg-gray-700"
                                                        >
                                                            <tr>
                                                                <th
                                                                    class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Waktu Input
                                                                </th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Jenis Reject
                                                                </th>
                                                                <th
                                                                    class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Qty Reject
                                                                </th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Alasan /
                                                                    Keterangan
                                                                </th>
                                                                <th
                                                                    class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Diinput Oleh
                                                                </th>
                                                                <th
                                                                    class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300"
                                                                >
                                                                    Aksi
                                                                </th>
                                                            </tr>
                                                        </thead>
                                                        <tbody
                                                            class="divide-y divide-gray-100 dark:divide-gray-700/50"
                                                        >
                                                            <tr
                                                                v-for="rd in item.reject_details"
                                                                :key="rd.id"
                                                                class="hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                                            >
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400"
                                                                >
                                                                    {{
                                                                        new Date(
                                                                            rd.created_at,
                                                                        ).toLocaleString(
                                                                            'id-ID',
                                                                            {
                                                                                dateStyle:
                                                                                    'short',
                                                                                timeStyle:
                                                                                    'short',
                                                                            },
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2"
                                                                >
                                                                    <div
                                                                        class="flex flex-wrap items-center gap-1.5"
                                                                    >
                                                                        <span
                                                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                                                            :class="
                                                                                getJenisBadge(
                                                                                    rd.jenis_reject,
                                                                                )
                                                                                    .class
                                                                            "
                                                                        >
                                                                            {{
                                                                                getJenisBadge(
                                                                                    rd.jenis_reject,
                                                                                )
                                                                                    .label
                                                                            }}
                                                                        </span>
                                                                        <span
                                                                            v-if="
                                                                                rd.odoo_scrap_id
                                                                            "
                                                                            class="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                                                            title="Data tersinkron dari Odoo MO"
                                                                        >
                                                                            <svg
                                                                                class="h-2.5 w-2.5"
                                                                                fill="currentColor"
                                                                                viewBox="0 0 20 20"
                                                                            >
                                                                                <path
                                                                                    fill-rule="evenodd"
                                                                                    d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                                    clip-rule="evenodd"
                                                                                />
                                                                            </svg>
                                                                            Odoo
                                                                            MO
                                                                        </span>
                                                                    </div>
                                                                </td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 text-right font-bold text-red-600 dark:text-red-400"
                                                                >
                                                                    {{
                                                                        rd.jumlah.toLocaleString(
                                                                            'id-ID',
                                                                        )
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="px-3 py-2 text-gray-800 dark:text-gray-200"
                                                                >
                                                                    {{
                                                                        rd.keterangan ||
                                                                        '-'
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-400"
                                                                >
                                                                    {{
                                                                        rd
                                                                            .creator
                                                                            ?.name ||
                                                                        'Leader'
                                                                    }}
                                                                </td>
                                                                <td
                                                                    class="whitespace-nowrap px-3 py-2 text-right"
                                                                >
                                                                    <button
                                                                        v-if="
                                                                            canDeleteReject(
                                                                                rd,
                                                                            )
                                                                        "
                                                                        type="button"
                                                                        @click.stop="
                                                                            openDeleteModal(
                                                                                rd,
                                                                            )
                                                                        "
                                                                        class="text-red-600 hover:text-red-800 hover:underline dark:text-red-400"
                                                                    >
                                                                        Hapus
                                                                    </button>
                                                                    <span
                                                                        v-else
                                                                        class="text-gray-400"
                                                                        >-</span
                                                                    >
                                                                </td>
                                                            </tr>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MOBILE CARD VIEW (md:hidden) -->
                <div class="block space-y-3 md:hidden">
                    <div
                        v-if="productions.data.length === 0"
                        class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                    >
                        Tidak ada data produksi
                    </div>

                    <div
                        v-for="item in productions.data"
                        :key="item.id"
                        class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm transition dark:border-gray-700 dark:bg-gray-800"
                    >
                        <!-- Top Batch & Date Header -->
                        <div
                            class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700"
                        >
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-bold text-gray-900 dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {{ item.batch_number }}
                                    </span>
                                    <span
                                        class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            prosesLabel[item.proses] ??
                                            item.proses
                                        }}
                                    </span>
                                </div>
                                <h3
                                    class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {{ item.produk?.nama_produk ?? '-' }}
                                </h3>
                            </div>
                            <span
                                class="text-[11px] font-medium text-gray-500 dark:text-gray-400"
                            >
                                {{ formatDate(item.tanggal) }}
                            </span>
                        </div>

                        <!-- 3-Column Metrics Grid -->
                        <div
                            class="my-2.5 grid grid-cols-3 gap-2 text-center text-xs"
                        >
                            <div
                                class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                            >
                                <span
                                    class="block text-[10px] text-gray-500 dark:text-gray-400"
                                    >Tersedia</span
                                >
                                <span
                                    class="font-bold text-gray-900 dark:text-gray-100"
                                    >{{
                                        item.available_qty.toLocaleString(
                                            'id-ID',
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="rounded-lg bg-red-50/60 p-2 dark:bg-red-950/30"
                            >
                                <span
                                    class="block text-[10px] text-red-600 dark:text-red-400"
                                    >Reject</span
                                >
                                <span
                                    class="font-bold text-red-600 dark:text-red-400"
                                    >{{
                                        item.total_reject.toLocaleString(
                                            'id-ID',
                                        )
                                    }}</span
                                >
                            </div>
                            <div
                                class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                            >
                                <span
                                    class="block text-[10px] text-gray-500 dark:text-gray-400"
                                    >Sisa</span
                                >
                                <span
                                    class="font-bold"
                                    :class="
                                        item.sisa_qty === 0
                                            ? 'text-red-600'
                                            : 'text-green-600'
                                    "
                                    >{{
                                        item.sisa_qty.toLocaleString('id-ID')
                                    }}</span
                                >
                            </div>
                        </div>

                        <!-- Action Row -->
                        <div
                            class="flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5 dark:border-gray-700"
                        >
                            <button
                                type="button"
                                @click="toggleBatchExpand(item.id)"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400"
                            >
                                <svg
                                    class="h-3.5 w-3.5 transition-transform"
                                    :class="{
                                        'rotate-90 text-red-600':
                                            expandedBatches[item.id],
                                    }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M9 5l7 7-7 7"
                                    />
                                </svg>
                                <span
                                    >Detail ({{
                                        item.reject_details?.length ?? 0
                                    }})</span
                                >
                            </button>

                            <button
                                v-if="!isManager"
                                :disabled="item.sisa_qty === 0"
                                @click="openReject(item)"
                                class="inline-flex items-center rounded-lg px-3 py-1 text-xs font-bold shadow-sm transition"
                                :class="
                                    item.sisa_qty === 0
                                        ? 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-700'
                                        : 'bg-red-600 text-white hover:bg-red-500 active:scale-95 dark:bg-red-500'
                                "
                            >
                                + Reject
                            </button>
                        </div>

                        <!-- Mobile Expandable Detail Cards -->
                        <div
                            v-if="expandedBatches[item.id]"
                            class="mt-3 border-t border-dashed border-gray-200 pt-3 dark:border-gray-700"
                        >
                            <div
                                v-if="
                                    !item.reject_details ||
                                    item.reject_details.length === 0
                                "
                                class="py-3 text-center text-xs text-gray-400"
                            >
                                Belum ada item reject pada batch ini.
                            </div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="rd in item.reject_details"
                                    :key="rd.id"
                                    class="rounded-lg bg-gray-50 p-2.5 text-xs dark:bg-gray-900/50"
                                >
                                    <div
                                        class="flex items-center justify-between"
                                    >
                                        <div
                                            class="flex items-center gap-1.5"
                                        >
                                            <span
                                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                                :class="
                                                    getJenisBadge(
                                                        rd.jenis_reject,
                                                    ).class
                                                "
                                            >
                                                {{
                                                    getJenisBadge(
                                                        rd.jenis_reject,
                                                    ).label
                                                }}
                                            </span>
                                            <span
                                                v-if="rd.odoo_scrap_id"
                                                class="inline-flex items-center rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                            >
                                                Odoo MO
                                            </span>
                                        </div>
                                        <span
                                            class="font-bold text-red-600 dark:text-red-400"
                                            >{{
                                                rd.jumlah.toLocaleString(
                                                    'id-ID',
                                                )
                                            }}
                                            pcs</span
                                        >
                                    </div>
                                    <p
                                        class="mt-1 text-gray-800 dark:text-gray-200"
                                    >
                                        <span
                                            class="text-gray-500 dark:text-gray-400"
                                            >Alasan:</span
                                        >
                                        {{ rd.keterangan || '-' }}
                                    </p>
                                    <div
                                        class="mt-1.5 flex items-center justify-between text-[11px] text-gray-400"
                                    >
                                        <span
                                            >{{
                                                new Date(
                                                    rd.created_at,
                                                ).toLocaleString('id-ID', {
                                                    dateStyle: 'short',
                                                    timeStyle: 'short',
                                                })
                                            }}
                                            ·
                                            {{
                                                rd.creator?.name || 'Leader'
                                            }}</span
                                        >
                                        <button
                                            v-if="canDeleteReject(rd)"
                                            type="button"
                                            @click="openDeleteModal(rd)"
                                            class="font-semibold text-red-600 hover:underline dark:text-red-400"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination -->
                <div
                    class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg"
                >
                    <Pagination
                        :current-page="productions.current_page"
                        :last-page="productions.last_page"
                        :total="productions.total"
                        :per-page="productions.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 2: ODOO SCRAP ORDERS (LIVE)            -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'odoo'">
                <!-- Top Toolbar Responsive -->
                <div
                    class="mb-4 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div
                        class="flex flex-1 flex-col items-stretch gap-2.5 sm:flex-row sm:items-center"
                    >
                        <div class="relative w-full sm:w-80">
                            <input
                                v-model="odooSearch"
                                type="text"
                                placeholder="Cari no scrap, no batch, produk..."
                                class="w-full rounded-md border-gray-300 pl-9 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm"
                            />
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                            >
                                <svg
                                    class="h-4 w-4 text-gray-400"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-1.5 text-xs text-gray-600 dark:text-gray-400 sm:justify-start"
                        >
                            <span>Tampilkan:</span>
                            <select
                                v-model="odooPerPage"
                                @change="odooCurrentPage = 1"
                                class="rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option :value="10">10 / hal</option>
                                <option :value="15">15 / hal</option>
                                <option :value="25">25 / hal</option>
                                <option :value="50">50 / hal</option>
                                <option :value="100">100 / hal</option>
                            </select>
                        </div>
                    </div>

                    <div class="flex items-center">
                        <button
                            type="button"
                            @click="fetchOdooScraps"
                            :disabled="isLoadingOdooScraps"
                            class="inline-flex w-full items-center justify-center gap-1.5 rounded-md bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-red-500 disabled:opacity-50 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700 dark:hover:bg-gray-700 sm:w-auto"
                        >
                            <svg
                                class="h-4 w-4 text-red-600"
                                :class="{ 'animate-spin': isLoadingOdooScraps }"
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
                            {{
                                isLoadingOdooScraps
                                    ? 'Memuat...'
                                    : 'Muat Ulang dari Odoo'
                            }}
                        </button>
                    </div>
                </div>

                <!-- Info Box -->
                <div
                    class="mb-4 flex flex-col justify-between gap-1.5 rounded-lg border border-red-100 bg-red-50/50 p-3 text-xs text-red-800 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-300 sm:flex-row sm:items-center"
                >
                    <div class="flex items-center gap-2">
                        <span
                            class="inline-block h-2 w-2 animate-pulse rounded-full bg-red-500"
                        ></span>
                        <span
                            >💡
                            <em
                                >Klik pada baris/kartu untuk membuka detail
                                Scrap Order Odoo.</em
                            ></span
                        >
                    </div>
                    <span
                        v-if="filteredOdooScraps.length > 0"
                        class="font-medium text-red-900 dark:text-red-200"
                    >
                        Total: {{ filteredOdooScraps.length }} Scrap Order
                    </span>
                </div>

                <!-- Error Message -->
                <div
                    v-if="odooScrapError"
                    class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg
                                class="h-5 w-5 text-red-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                            <span>{{ odooScrapError }}</span>
                        </div>
                        <button
                            @click="fetchOdooScraps"
                            class="font-semibold underline hover:text-red-900 dark:hover:text-red-100"
                        >
                            Coba Lagi
                        </button>
                    </div>
                </div>

                <!-- DESKTOP TABLE VIEW ODOO (md and up) -->
                <div
                    class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block"
                >
                    <div class="overflow-x-auto">
                        <table
                            class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm"
                        >
                            <thead class="bg-gray-50 dark:bg-gray-700/70">
                                <tr>
                                    <th
                                        class="w-8 px-2 py-2.5 text-center text-xs font-semibold uppercase text-gray-400"
                                    ></th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        No. Dokumen
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        No. Batch
                                    </th>
                                    <th
                                        class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Produk
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Qty Reject
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Tanggal
                                    </th>
                                    <th
                                        class="whitespace-nowrap px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                    >
                                        Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                            >
                                <tr v-if="isLoadingOdooScraps">
                                    <td
                                        colspan="7"
                                        class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center gap-2"
                                        >
                                            <svg
                                                class="h-6 w-6 animate-spin text-red-600"
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
                                            <span
                                                >Menghubungi Odoo ERP dan
                                                mengambil data Scrap...</span
                                            >
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-else-if="paginatedOdooScraps.length === 0"
                                >
                                    <td
                                        colspan="7"
                                        class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        <div
                                            class="flex flex-col items-center justify-center gap-1"
                                        >
                                            <svg
                                                class="h-8 w-8 text-gray-400"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"
                                                />
                                            </svg>
                                            <p
                                                class="font-medium text-gray-700 dark:text-gray-300"
                                            >
                                                Tidak ada data Scrap Order di
                                                Odoo
                                            </p>
                                            <p class="text-xs text-gray-400">
                                                Pastikan modul Inventory Odoo
                                                aktif atau lakukan transaksi
                                                reject dari LinePulse.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                                <template
                                    v-for="scrap in paginatedOdooScraps"
                                    :key="scrap.id"
                                >
                                    <tr
                                        @click="toggleOdooScrapExpand(scrap.id)"
                                        class="cursor-pointer transition hover:bg-red-50/40 dark:hover:bg-gray-700/60"
                                        :class="{
                                            'bg-red-50/20 dark:bg-gray-700/30':
                                                expandedOdooScraps[scrap.id],
                                        }"
                                    >
                                        <td
                                            class="px-2 py-2.5 text-center text-gray-400"
                                        >
                                            <svg
                                                class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                                :class="{
                                                    'rotate-90 text-red-600':
                                                        expandedOdooScraps[
                                                            scrap.id
                                                        ],
                                                }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    stroke-width="2"
                                                    d="M9 5l7 7-7 7"
                                                />
                                            </svg>
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-red-600 dark:text-red-400"
                                        >
                                            {{ scrap.name }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-gray-900 dark:text-gray-100"
                                        >
                                            <span
                                                class="rounded bg-red-50 px-2 py-0.5 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300"
                                            >
                                                {{ scrap.batch_number }}
                                            </span>
                                        </td>
                                        <td
                                            class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100"
                                        >
                                            {{ scrap.product_name }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-right font-bold text-red-600 dark:text-red-400"
                                        >
                                            {{
                                                Number(
                                                    scrap.scrap_qty,
                                                ).toLocaleString('id-ID')
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-gray-600 dark:text-gray-300"
                                        >
                                            {{
                                                scrap.date
                                                    ? formatDate(scrap.date)
                                                    : '-'
                                            }}
                                        </td>
                                        <td
                                            class="whitespace-nowrap px-3 py-2.5 text-center"
                                        >
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                                :class="
                                                    scrap.state === 'done'
                                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                                "
                                            >
                                                {{ scrap.state }}
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- DROPDOWN ACCORDION DETAIL SCRAP ORDER ODOO -->
                                    <tr
                                        v-if="expandedOdooScraps[scrap.id]"
                                        class="bg-gray-50/90 dark:bg-gray-900/60"
                                    >
                                        <td colspan="7" class="p-3 sm:p-4">
                                            <div
                                                class="rounded-xl border border-red-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                                            >
                                                <div
                                                    class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700"
                                                >
                                                    <div
                                                        class="flex items-center gap-2"
                                                    >
                                                        <span
                                                            class="text-sm font-bold text-gray-900 dark:text-gray-100"
                                                        >
                                                            Detail Dokumen Odoo:
                                                            <span
                                                                class="font-mono text-red-600 dark:text-red-400"
                                                                >{{
                                                                    scrap.name
                                                                }}</span
                                                            >
                                                        </span>
                                                        <span
                                                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase"
                                                            :class="
                                                                scrap.state ===
                                                                'done'
                                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                                                    : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                                            "
                                                        >
                                                            {{ scrap.state }}
                                                        </span>
                                                    </div>
                                                    <span
                                                        class="text-xs text-gray-400"
                                                        >ID Record Odoo: #{{
                                                            scrap.id
                                                        }}</span
                                                    >
                                                </div>

                                                <div
                                                    class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2 md:grid-cols-4"
                                                >
                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            🏷️ Nomor Batch (Lot
                                                            / Serial)
                                                        </p>
                                                        <p
                                                            class="mt-1 font-mono text-sm font-bold text-red-600 dark:text-red-400"
                                                        >
                                                            {{
                                                                scrap.batch_number
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            📦 Produk
                                                        </p>
                                                        <p
                                                            class="mt-1 font-semibold text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{
                                                                scrap.product_name
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            🔢 Jumlah Reject
                                                        </p>
                                                        <p
                                                            class="mt-1 text-sm font-bold text-red-600 dark:text-red-400"
                                                        >
                                                            {{
                                                                Number(
                                                                    scrap.scrap_qty,
                                                                ).toLocaleString(
                                                                    'id-ID',
                                                                )
                                                            }}
                                                            {{ scrap.uom }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            📅 Tanggal Scrap
                                                        </p>
                                                        <p
                                                            class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{
                                                                scrap.date
                                                                    ? formatDate(
                                                                          scrap.date,
                                                                      )
                                                                    : '-'
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            📍 Lokasi Asal
                                                            (Source)
                                                        </p>
                                                        <p
                                                            class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{ scrap.location }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            🗑️ Lokasi Pemusnahan
                                                            (Scrap)
                                                        </p>
                                                        <p
                                                            class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{
                                                                scrap.scrap_location
                                                            }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            👤 Dicatat Oleh
                                                        </p>
                                                        <p
                                                            class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{ scrap.creator }}
                                                        </p>
                                                    </div>

                                                    <div
                                                        class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40"
                                                    >
                                                        <p
                                                            class="font-medium text-gray-500 dark:text-gray-400"
                                                        >
                                                            📝 Referensi Dokumen
                                                            (Origin)
                                                        </p>
                                                        <p
                                                            class="mt-1 font-mono text-gray-900 dark:text-gray-100"
                                                        >
                                                            {{ scrap.origin }}
                                                        </p>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MOBILE CARD VIEW ODOO (md:hidden) -->
                <div class="block space-y-3 md:hidden">
                    <div
                        v-if="isLoadingOdooScraps"
                        class="rounded-xl border border-gray-200 bg-white p-8 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="flex flex-col items-center justify-center gap-2"
                        >
                            <svg
                                class="h-6 w-6 animate-spin text-red-600"
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
                            <span>Memuat data dari Odoo ERP...</span>
                        </div>
                    </div>

                    <div
                        v-else-if="paginatedOdooScraps.length === 0"
                        class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                    >
                        Tidak ada data Scrap Order di Odoo.
                    </div>

                    <div
                        v-else
                        v-for="scrap in paginatedOdooScraps"
                        :key="scrap.id"
                        class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm transition dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div
                            class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700"
                        >
                            <div>
                                <span
                                    class="font-mono text-sm font-bold text-red-600 dark:text-red-400"
                                    >{{ scrap.name }}</span
                                >
                                <h3
                                    class="mt-0.5 text-sm font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {{ scrap.product_name }}
                                </h3>
                            </div>
                            <span
                                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize"
                                :class="
                                    scrap.state === 'done'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                        : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'
                                "
                            >
                                {{ scrap.state }}
                            </span>
                        </div>

                        <div class="my-2.5 grid grid-cols-2 gap-2 text-xs">
                            <div
                                class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                            >
                                <span
                                    class="block text-[10px] text-gray-500 dark:text-gray-400"
                                    >Batch / Lot</span
                                >
                                <span
                                    class="font-mono font-bold text-red-600 dark:text-red-400"
                                    >{{ scrap.batch_number }}</span
                                >
                            </div>
                            <div
                                class="rounded-lg bg-red-50/60 p-2 text-right dark:bg-red-950/30"
                            >
                                <span
                                    class="block text-[10px] text-red-600 dark:text-red-400"
                                    >Qty Reject</span
                                >
                                <span
                                    class="font-bold text-red-600 dark:text-red-400"
                                    >{{
                                        Number(scrap.scrap_qty).toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                    {{ scrap.uom }}</span
                                >
                            </div>
                        </div>

                        <div
                            class="flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
                        >
                            <span
                                >📅
                                {{
                                    scrap.date ? formatDate(scrap.date) : '-'
                                }}</span
                            >
                            <button
                                type="button"
                                @click="toggleOdooScrapExpand(scrap.id)"
                                class="inline-flex items-center gap-1 font-semibold text-red-600 hover:text-red-700 dark:text-red-400"
                            >
                                <span>{{
                                    expandedOdooScraps[scrap.id]
                                        ? 'Tutup Detail'
                                        : 'Lihat Detail'
                                }}</span>
                                <svg
                                    class="h-3.5 w-3.5 transition-transform"
                                    :class="{
                                        'rotate-180':
                                            expandedOdooScraps[scrap.id],
                                    }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M19 9l-7 7-7-7"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- Expanded Odoo Scrap Mobile Detail (Full Desktop Matching) -->
                        <div
                            v-if="expandedOdooScraps[scrap.id]"
                            class="mt-3 space-y-2.5 border-t border-dashed border-gray-200 pt-3 dark:border-gray-700"
                        >
                            <div
                                class="flex items-center justify-between text-[11px]"
                            >
                                <span
                                    class="font-semibold text-gray-700 dark:text-gray-300"
                                    >Detail Dokumen Odoo:</span
                                >
                                <span class="font-mono text-gray-400"
                                    >ID Record: #{{ scrap.id }}</span
                                >
                            </div>

                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >🏷️ No. Batch</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate font-mono text-xs font-bold text-red-600 dark:text-red-400"
                                        >{{ scrap.batch_number }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >📦 Produk</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate text-[11px] font-semibold text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{ scrap.product_name }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >🔢 Qty Reject</span
                                    >
                                    <span
                                        class="mt-0.5 block text-xs font-bold text-red-600 dark:text-red-400"
                                    >
                                        {{
                                            Number(
                                                scrap.scrap_qty,
                                            ).toLocaleString('id-ID')
                                        }}
                                        {{ scrap.uom }}
                                    </span>
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >📅 Tgl Scrap</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{
                                            scrap.date
                                                ? formatDate(scrap.date)
                                                : '-'
                                        }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >📍 Lokasi Asal</span
                                    >
                                    <span
                                        class="mt-0.5 block break-words text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{ scrap.location }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >🗑️ Lokasi Scrap</span
                                    >
                                    <span
                                        class="mt-0.5 block break-words text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{ scrap.scrap_location }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >👤 Dicatat Oleh</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{ scrap.creator }}</span
                                    >
                                </div>

                                <div
                                    class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50 sm:p-2.5"
                                >
                                    <span
                                        class="block text-[10px] font-medium text-gray-500 dark:text-gray-400 sm:text-[10.5px]"
                                        >📝 Origin Ref</span
                                    >
                                    <span
                                        class="mt-0.5 block truncate font-mono text-[11px] font-medium text-gray-900 dark:text-gray-100 sm:text-xs"
                                        >{{ scrap.origin }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination for Odoo Live Scraps -->
                <div
                    v-if="filteredOdooScraps.length > 0"
                    class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg"
                >
                    <Pagination
                        :current-page="odooCurrentPage"
                        :last-page="odooLastPage"
                        :total="filteredOdooScraps.length"
                        :per-page="odooPerPage"
                        @page="(p) => (odooCurrentPage = p)"
                    />
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODAL FORM INPUT REJECT                    -->
        <!-- ========================================== -->
        <Modal :show="showModal" maxWidth="lg" @close="closeModal">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Form Reject Produk
                </h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Lengkapi data reject. Data produksi di bawah bersifat
                    readonly.
                </p>

                <!-- Info Produk -->
                <div
                    v-if="selected"
                    class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30"
                >
                    <h3
                        class="mb-2 text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                    >
                        Informasi Batch & Produk
                    </h3>
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                No. Batch
                            </dt>
                            <dd
                                class="mt-0.5 font-mono font-bold text-red-600 dark:text-red-400"
                            >
                                {{ selected.batch_number }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                Nama Produk
                            </dt>
                            <dd
                                class="mt-0.5 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ selected.produk?.nama_produk }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                Process
                            </dt>
                            <dd
                                class="mt-0.5 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    prosesLabel[selected.proses] ??
                                    selected.proses
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                Tanggal Process
                            </dt>
                            <dd
                                class="mt-0.5 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ formatDate(selected.tanggal) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                Qty Tersedia
                            </dt>
                            <dd
                                class="mt-0.5 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{
                                    selected.available_qty.toLocaleString(
                                        'id-ID',
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400">
                                Sisa yang dapat direject
                            </dt>
                            <dd
                                class="mt-0.5 font-semibold"
                                :class="
                                    selected.sisa_qty === 0
                                        ? 'text-red-600'
                                        : 'text-green-600'
                                "
                            >
                                {{ selected.sisa_qty.toLocaleString('id-ID') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel value="Alasan / Keterangan Reject" />
                        <textarea
                            v-model="form.alasan_reject"
                            rows="3"
                            placeholder="Masukkan detail alasan reject..."
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                        ></textarea>
                        <InputError
                            :message="errors.alasan_reject"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Qty Reject (Pcs)" />
                        <TextInput
                            v-model="form.qty_reject"
                            type="number"
                            min="1"
                            step="1"
                            class="no-spinner mt-1 block w-full"
                            placeholder="0"
                        />
                        <p
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Maksimal reject: {{ selected?.sisa_qty ?? 0 }} pcs
                        </p>
                        <InputError :message="errors.qty_reject" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Jenis Reject" />
                        <select
                            v-model="form.jenis_reject"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="" disabled>
                                Pilih Jenis Reject
                            </option>
                            <option
                                v-for="opt in jenisOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <p
                            v-if="form.jenis_reject === 'process' && selected"
                            class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                        >
                            Akan disimpan sebagai reject proses:
                            <span class="font-semibold">{{
                                prosesLabel[selected.proses] ?? selected.proses
                            }}</span>
                            (otomatis dari data produksi)
                        </p>
                        <InputError
                            :message="errors.jenis_reject"
                            class="mt-1"
                        />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton @click="closeModal">Batal</SecondaryButton>
                    <PrimaryButton @click="onSubmit" :disabled="isSubmitting">{{
                        isSubmitting ? 'Menyimpan...' : 'Simpan Reject'
                    }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- MODAL KONFIRMASI SIMPAN REJECT             -->
        <!-- ========================================== -->
        <Modal :show="showConfirm" maxWidth="md" @close="showConfirm = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Konfirmasi Reject
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin melakukan reject terhadap produk
                    ini?
                </p>
                <div
                    v-if="selected"
                    class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/30"
                >
                    <p>
                        <span class="text-gray-500">No. Batch:</span>
                        <span
                            class="font-mono font-bold text-red-600 dark:text-red-400"
                            >{{ selected.batch_number }}</span
                        >
                    </p>
                    <p>
                        <span class="text-gray-500">Produk:</span>
                        <span
                            class="font-medium text-gray-900 dark:text-gray-100"
                            >{{ selected.produk?.nama_produk }}</span
                        >
                    </p>
                    <p>
                        <span class="text-gray-500">Qty Reject:</span>
                        <span class="font-bold text-red-600 dark:text-red-400"
                            >{{ form.qty_reject }} pcs</span
                        >
                    </p>
                    <p>
                        <span class="text-gray-500">Jenis Reject:</span>
                        <span
                            class="font-medium text-gray-900 dark:text-gray-100"
                            >{{ jenisLabel }}</span
                        >
                    </p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton
                        @click="showConfirm = false"
                        :disabled="isSubmitting"
                        >Batal</SecondaryButton
                    >
                    <DangerButton
                        @click="confirmSubmit"
                        :disabled="isSubmitting"
                        >{{
                            isSubmitting ? 'Menyimpan...' : 'Simpan Reject'
                        }}</DangerButton
                    >
                </div>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- MODAL KONFIRMASI HAPUS REJECT              -->
        <!-- ========================================== -->
        <Modal
            :show="showDeleteConfirm"
            maxWidth="md"
            @close="showDeleteConfirm = false"
        >
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                    Hapus Item Reject
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus catatan reject ini? Sisa
                    qty produksi akan dikembalikan.
                </p>
                <div
                    v-if="rejectToDelete"
                    class="mt-4 rounded-md border border-red-200 bg-red-50/50 p-3 text-sm dark:border-red-900/40 dark:bg-red-950/20"
                >
                    <p>
                        <span class="text-gray-500">Jumlah:</span>
                        <span class="font-bold text-red-600 dark:text-red-400"
                            >{{ rejectToDelete.jumlah }} pcs</span
                        >
                    </p>
                    <p>
                        <span class="text-gray-500">Alasan:</span>
                        <span class="text-gray-800 dark:text-gray-200">{{
                            rejectToDelete.keterangan || '-'
                        }}</span>
                    </p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton
                        @click="showDeleteConfirm = false"
                        :disabled="isDeleting"
                        >Batal</SecondaryButton
                    >
                    <DangerButton
                        @click="confirmDeleteReject"
                        :disabled="isDeleting"
                        >{{
                            isDeleting ? 'Menghapus...' : 'Ya, Hapus Data'
                        }}</DangerButton
                    >
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
