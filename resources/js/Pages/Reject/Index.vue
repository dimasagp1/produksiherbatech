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

interface Produk {
    id: number;
    nama_produk: string;
    kode_produk?: string;
    odoo_uom?: string;
}

interface RejectDetailItem {
    id: number;
    laporan_harian_id: number;
    material_name?: string | null;
    material_uom?: string | null;
    odoo_mo_id?: number | null;
    odoo_mo_name?: string | null;
    jenis_reject: 'sublayer' | 'ga' | 'process';
    jumlah: number;
    keterangan: string | null;
    odoo_scrap_id: number | null;
    odoo_synced_at: string | null;
    created_at: string;
    created_by: number;
    creator?: UserCreator;
    laporan_harian?: {
        id: number;
        batch_number: string;
        proses: string;
        tanggal: string;
        produk?: Produk;
    };
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
    rejectDetails?: {
        data: RejectDetailItem[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    produks?: { id: number; kode_produk: string; nama_produk: string }[];
    search: string;
    filter?: 'all' | 'has_reject' | 'odoo' | 'ga' | 'sublayer' | 'process';
    month?: string;
    start_date?: string;
    end_date?: string;
    produk_id?: number | string;
    sort_by?: string;
    stats?: {
        total_reject_pcs?: number;
        total_reject_ga?: number;
        total_reject_sup?: number;
        total_reject_loss?: number;
        total_records?: number;
    };
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

// Tabs: 3 Main Views
const activeTab = ref<'local' | 'breakdown' | 'odoo'>('local');
const search = ref(props.search ?? '');
const currentFilter = ref(props.filter ?? 'all');
const selectedMonth = ref(props.month ?? '');
const selectedStartDate = ref(props.start_date ?? '');
const selectedEndDate = ref(props.end_date ?? '');
const selectedProdukId = ref<string | number>(props.produk_id ?? '');
const selectedSortBy = ref(props.sort_by ?? 'tanggal_desc');

const selected = ref<LaporanItem | null>(null);
const showModal = ref(false);
const showConfirm = ref(false);
const isSubmitting = ref(false);

// Accordion states
const expandedBatches = ref<Record<number, boolean>>({});
function toggleBatchExpand(id: number) {
    expandedBatches.value[id] = !expandedBatches.value[id];
}

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
    { value: 'sublayer', label: 'Reject Sublayer / Supplier' },
    { value: 'ga', label: 'Reject GA / QA' },
    { value: 'process', label: 'Reject Proses / Loss' },
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
                label: 'Reject Supplier',
                class: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
            };
        case 'ga':
            return {
                label: 'Reject QA / GA',
                class: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800',
            };
        case 'process':
            return {
                label: 'Reject Proses (Loss)',
                class: 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
            };
        default:
            return {
                label: jenis,
                class: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700',
            };
    }
}

function getItemCategory(name?: string | null): { type: 'kemas' | 'ruahan' | 'fg' | 'rm'; label: string; class: string; icon: string; canDecompose: boolean } {
    if (!name) return { type: 'fg', label: 'Produk Jadi', class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800', icon: '🏷️', canDecompose: true };
    const n = name.toLowerCase();
    if (n.includes('label') || n.includes('dusbox') || n.includes('botol') || n.includes('tutup') || n.includes('masterbox') || n.includes('segel') || n.includes('shrink') || n.includes('sendok') || n.includes('box') || n.includes('kemasan')) {
        return { type: 'kemas', label: 'Bahan Kemas', class: 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800', icon: '📦', canDecompose: false };
    }
    if (n.includes('ruahan')) {
        return { type: 'ruahan', label: 'Ruahan (WIP)', class: 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-950/40 dark:text-indigo-300 dark:border-indigo-800', icon: '🍶', canDecompose: true };
    }
    if (n.includes('ekstrak') || n.includes('minyak') || n.includes('madu') || n.includes('syrup') || n.includes('creamer') || n.includes('flavour') || n.includes('gum') || n.includes('foam') || n.includes('beras') || n.includes('milk') || n.includes('tcp') || n.includes('sugar') || n.includes('salt') || n.includes('curcuma')) {
        return { type: 'rm', label: 'Bahan Baku', class: 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/40 dark:text-teal-300 dark:border-teal-800', icon: '🧪', canDecompose: false };
    }
    return { type: 'fg', label: 'Produk Jadi', class: 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800', icon: '🏷️', canDecompose: true };
}

// Recipe Modal State
const showRecipeModal = ref(false);
const recipeLoading = ref(false);
const recipeError = ref<string | null>(null);
const activeRecipe = ref<any>(null);
const activeRecipeItem = ref<RejectDetailItem | null>(null);

async function openRecipeBreakdown(item: RejectDetailItem) {
    activeRecipeItem.value = item;
    showRecipeModal.value = true;
    recipeLoading.value = true;
    recipeError.value = null;
    activeRecipe.value = null;

    try {
        const matName = item.material_name || item.laporan_harian?.produk?.nama_produk || '';
        const parentName = item.laporan_harian?.produk?.nama_produk || '';
        const qty = item.jumlah || 1;

        const res = await axios.get(route('reject.recipe-breakdown'), {
            params: {
                material_name: matName,
                qty: qty,
                parent_product_name: parentName,
            },
        });

        if (res.data.success) {
            activeRecipe.value = res.data.data;
        } else {
            recipeError.value = res.data.message || 'Data formula tidak ditemukan.';
        }
    } catch (err: any) {
        recipeError.value = err.response?.data?.message || err.message || 'Gagal memuat formula komposisi bahan baku.';
    } finally {
        recipeLoading.value = false;
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
        if (!Number.isFinite(n) || n <= 0) {
            e.qty_reject = 'Qty reject harus lebih besar dari 0.';
        } else if (selected.value && n > selected.value.sisa_qty) {
            e.qty_reject = `Qty reject tidak boleh melebihi qty produk yang tersedia. Sisa: ${selected.value.sisa_qty}`;
        }
    }
    if (!form.value.jenis_reject) {
        e.jenis_reject = 'Jenis reject wajib dipilih.';
    }
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
                    getFilterParams(props.productions.current_page),
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

function getFilterParams(pageOverride = 1, rejectPageOverride?: number) {
    const p: Record<string, any> = {
        page: pageOverride,
    };
    if (rejectPageOverride) p.reject_page = rejectPageOverride;
    if (search.value && search.value.trim()) p.search = search.value.trim();
    if (currentFilter.value && currentFilter.value !== 'all') p.filter = currentFilter.value;
    if (selectedMonth.value) p.month = selectedMonth.value;
    if (selectedStartDate.value) p.start_date = selectedStartDate.value;
    if (selectedEndDate.value) p.end_date = selectedEndDate.value;
    if (selectedProdukId.value !== '' && selectedProdukId.value !== null) p.produk_id = selectedProdukId.value;
    if (selectedSortBy.value && selectedSortBy.value !== 'tanggal_desc') p.sort_by = selectedSortBy.value;
    return p;
}

function applyFilters() {
    router.get(route('reject.index'), getFilterParams(1), {
        preserveState: true,
        preserveScroll: true,
    });
}

function onMonthChange() {
    if (selectedMonth.value) {
        selectedStartDate.value = '';
        selectedEndDate.value = '';
    }
    applyFilters();
}

function onDateRangeChange() {
    if (selectedStartDate.value || selectedEndDate.value) {
        selectedMonth.value = '';
    }
    applyFilters();
}

function resetFilters() {
    search.value = '';
    currentFilter.value = 'all';
    selectedMonth.value = '';
    selectedStartDate.value = '';
    selectedEndDate.value = '';
    selectedProdukId.value = '';
    selectedSortBy.value = 'tanggal_desc';

    router.get(route('reject.index'), {}, {
        preserveState: false,
    });
}

function setFilter(newFilter: any) {
    currentFilter.value = newFilter;
    applyFilters();
}

function goToPage(pageNum: number) {
    router.get(route('reject.index'), getFilterParams(pageNum), {
        preserveState: true,
        preserveScroll: true,
    });
}

function goToRejectPage(pageNum: number) {
    router.get(route('reject.index'), getFilterParams(props.productions.current_page, pageNum), {
        preserveState: true,
        preserveScroll: true,
    });
}

function doSearch() {
    applyFilters();
}

const hasActiveFilters = computed(() => {
    return !!(
        (search.value && search.value.trim()) ||
        currentFilter.value !== 'all' ||
        selectedMonth.value ||
        selectedStartDate.value ||
        selectedEndDate.value ||
        (selectedProdukId.value !== '' && selectedProdukId.value !== null) ||
        (selectedSortBy.value && selectedSortBy.value !== 'tanggal_desc')
    );
});

const jenisLabel = computed(() => {
    const v = form.value.jenis_reject;
    if (v === 'sublayer') return 'Reject Sublayer / Supplier';
    if (v === 'ga') return 'Reject GA / QA';
    if (v === 'process')
        return `Reject Proses (${selected.value ? (prosesLabel[selected.value.proses] ?? selected.value.proses) : '-'})`;
    return '-';
});
</script>

<template>
    <Head title="Reject & Scrap Produksi" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Reject & Scrap Produksi
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Rincian komponen material reject per batch & produk dari MO Odoo (Hanya MO Status DONE)
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
                            : 'Role Leader · Data MO Odoo Terverifikasi'
                    }}</span>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8 py-2">
            <!-- Flash Message -->
            <div
                v-if="flashSuccess"
                class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-900/20 dark:text-green-200 flex items-center gap-2"
            >
                <svg class="h-5 w-5 text-green-600 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>{{ flashSuccess }}</span>
            </div>

            <!-- KPI Summary Cards -->
            <div class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div class="rounded-xl border border-red-200 bg-white p-3.5 shadow-sm dark:border-red-900/40 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase text-red-600 dark:text-red-400">Total Reject</span>
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-red-100 text-red-600 dark:bg-red-900/50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-gray-900 dark:text-gray-100">
                        {{ (stats?.total_reject_pcs || 0).toLocaleString('id-ID') }}
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ stats?.total_records || 0 }} item transaksi terdata
                    </p>
                </div>

                <div class="rounded-xl border border-amber-200 bg-white p-3.5 shadow-sm dark:border-amber-900/40 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase text-amber-600 dark:text-amber-400">Reject QA / GA</span>
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-amber-100 text-amber-600 dark:bg-amber-900/50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-gray-900 dark:text-gray-100">
                        {{ (stats?.total_reject_ga || 0).toLocaleString('id-ID') }}
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                        Reject hasil sampling QA
                    </p>
                </div>

                <div class="rounded-xl border border-blue-200 bg-white p-3.5 shadow-sm dark:border-blue-900/40 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase text-blue-600 dark:text-blue-400">Reject Supplier</span>
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-blue-100 text-blue-600 dark:bg-blue-900/50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-gray-900 dark:text-gray-100">
                        {{ (stats?.total_reject_sup || 0).toLocaleString('id-ID') }}
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                        Defect kemasan & bahan vendor
                    </p>
                </div>

                <div class="rounded-xl border border-purple-200 bg-white p-3.5 shadow-sm dark:border-purple-900/40 dark:bg-gray-800">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-semibold uppercase text-purple-600 dark:text-purple-400">Loss / Reject Proses</span>
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-purple-100 text-purple-600 dark:bg-purple-900/50">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                        </span>
                    </div>
                    <div class="mt-2 text-xl font-bold font-mono text-gray-900 dark:text-gray-100">
                        {{ (stats?.total_reject_loss || 0).toLocaleString('id-ID') }}
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                        Susut saat mixing/filling/packing
                    </p>
                </div>
            </div>

            <!-- Tab Switcher (Responsive) -->
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
                    <span>Rekap per Batch Produksi</span>
                </button>

                <button
                    type="button"
                    @click="activeTab = 'breakdown'"
                    class="flex flex-shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-xs font-semibold transition sm:text-sm"
                    :class="
                        activeTab === 'breakdown'
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
                            d="M4 6h16M4 10h16M4 14h16M4 18h16"
                        />
                    </svg>
                    <span>Rincian per Komponen / Material</span>
                    <span
                        v-if="rejectDetails?.total"
                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                    >
                        {{ rejectDetails.total }}
                    </span>
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
                    <span>Scrap Order Odoo (Live)</span>
                    <span
                        v-if="odooScraps.length > 0"
                        class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/40 dark:text-red-300"
                    >
                        {{ odooScraps.length }}
                    </span>
                </button>
            </div>

            <!-- Global Action & Filter Toolbar -->
            <div
                v-show="activeTab !== 'odoo'"
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-1 flex-col gap-2 sm:flex-row sm:items-center">
                    <SearchInput
                        v-model="search"
                        placeholder="Cari produk, material reject, no batch, no MO..."
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
                                : 'Sync Reject dari MO Odoo (DONE)'
                        }}</span>
                    </button>
                </div>
                <p class="text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">
                    💡 <em>Hanya Manufacturing Order berstatus <strong>DONE</strong> yang disinkronkan.</em>
                </p>
            </div>

            <!-- Filter & Sorting Box -->
            <div
                v-show="activeTab !== 'odoo'"
                class="mb-4 rounded-xl border border-gray-200 bg-white p-3.5 sm:p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                    <div class="flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-gray-800 dark:text-gray-200">
                                Filter & Sort Data Reject
                            </h3>
                            <p class="text-[11px] text-gray-500 dark:text-gray-400">
                                Saring per bulan, rentang tanggal, produk jadi, dan jenis reject
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="hasActiveFilters"
                            type="button"
                            @click="resetFilters"
                            class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-xs hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                            <span>Reset Filter</span>
                        </button>
                    </div>
                </div>

                <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">📅 Filter per Bulan</label>
                        <input
                            type="month"
                            v-model="selectedMonth"
                            @change="onMonthChange"
                            class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        />
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">🗓️ Rentang Tanggal</label>
                        <div class="flex items-center gap-1">
                            <input
                                type="date"
                                v-model="selectedStartDate"
                                @change="onDateRangeChange"
                                placeholder="Mulai"
                                class="w-1/2 rounded-lg border-gray-300 text-[11px] p-1.5 shadow-xs focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            />
                            <span class="text-xs text-gray-400">-</span>
                            <input
                                type="date"
                                v-model="selectedEndDate"
                                @change="onDateRangeChange"
                                placeholder="Selesai"
                                class="w-1/2 rounded-lg border-gray-300 text-[11px] p-1.5 shadow-xs focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            />
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">📦 Filter Sesuai Item / Produk Jadi</label>
                        <select
                            v-model="selectedProdukId"
                            @change="applyFilters"
                            class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="">Semua Produk / Item</option>
                            <option v-for="p in produks" :key="p.id" :value="p.id">
                                {{ p.kode_produk ? p.kode_produk + ' - ' : '' }}{{ p.nama_produk }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-600 dark:text-gray-400 mb-1">↕️ Urutkan Data (Sort)</label>
                        <select
                            v-model="selectedSortBy"
                            @change="applyFilters"
                            class="w-full rounded-lg border-gray-300 text-xs shadow-xs focus:border-red-500 focus:ring-red-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                        >
                            <option value="tanggal_desc">📅 Tanggal Terbaru</option>
                            <option value="tanggal_asc">📅 Tanggal Terlama</option>
                            <option value="reject_desc">🔻 Reject Terbanyak</option>
                            <option value="reject_asc">🔺 Reject Tersedikit</option>
                            <option value="batch_asc">🏷️ No. Batch (A - Z)</option>
                            <option value="batch_desc">🏷️ No. Batch (Z - A)</option>
                            <option value="produk_asc">📦 Nama Produk (A - Z)</option>
                        </select>
                    </div>
                </div>

                <!-- Quick Filter Badges -->
                <div class="mt-3.5 flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700">
                    <button
                        type="button"
                        @click="setFilter('all')"
                        class="rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                        :class="currentFilter === 'all' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        Semua Data
                    </button>
                    <button
                        type="button"
                        @click="setFilter('ga')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                        :class="currentFilter === 'ga' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 border border-amber-200 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:border-amber-800'"
                    >
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                        Reject QA / GA
                    </button>
                    <button
                        type="button"
                        @click="setFilter('sublayer')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                        :class="currentFilter === 'sublayer' ? 'bg-blue-600 text-white' : 'bg-blue-50 text-blue-800 border border-blue-200 hover:bg-blue-100 dark:bg-blue-950/40 dark:text-blue-300 dark:border-blue-800'"
                    >
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        Reject Supplier
                    </button>
                    <button
                        type="button"
                        @click="setFilter('process')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                        :class="currentFilter === 'process' ? 'bg-purple-600 text-white' : 'bg-purple-50 text-purple-800 border border-purple-200 hover:bg-purple-100 dark:bg-purple-950/40 dark:text-purple-300 dark:border-purple-800'"
                    >
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-purple-500"></span>
                        Loss / Reject Proses
                    </button>
                    <button
                        type="button"
                        @click="setFilter('odoo')"
                        class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                        :class="currentFilter === 'odoo' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800'"
                    >
                        <span class="inline-block h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Tersinkron MO Odoo
                    </button>
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 1: REKAP PER BATCH PRODUKSI            -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'local'">
                <!-- DESKTOP TABLE VIEW (md and up) -->
                <div class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm min-w-[750px]">
                            <thead class="bg-gray-50 dark:bg-gray-700/70">
                                <tr>
                                    <th class="w-8 px-2 py-2.5 text-center text-xs font-semibold uppercase text-gray-400"></th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        No. Batch
                                    </th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Produk Jadi
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Process
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Tanggal
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Qty Tersedia
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Total Reject
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Sisa
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr v-if="productions.data.length === 0">
                                    <td colspan="9" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data produksi
                                    </td>
                                </tr>
                                <template v-for="item in productions.data" :key="item.id">
                                    <tr
                                        @click="toggleBatchExpand(item.id)"
                                        class="cursor-pointer transition hover:bg-red-50/40 dark:hover:bg-gray-700/60"
                                        :class="{ 'bg-red-50/20 dark:bg-gray-700/30': expandedBatches[item.id] }"
                                    >
                                        <td class="px-2 py-2.5 text-center text-gray-400">
                                            <svg
                                                class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                                :class="{ 'rotate-90 text-red-600': expandedBatches[item.id] }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-gray-900 dark:text-gray-100">
                                            <span class="rounded bg-gray-100 px-2 py-0.5 text-xs text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                                                {{ item.batch_number }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {{ item.produk?.nama_produk ?? '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-700 dark:text-gray-200">
                                            <span class="rounded bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                                {{ prosesLabel[item.proses] ?? item.proses }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600 dark:text-gray-300">
                                            {{ formatDate(item.tanggal) }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-medium text-gray-900 dark:text-gray-100">
                                            {{ item.available_qty.toLocaleString('id-ID') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold" :class="item.total_reject > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-400'">
                                            {{ item.total_reject.toLocaleString('id-ID') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-semibold" :class="item.sisa_qty === 0 ? 'text-red-600' : 'text-green-600'">
                                            {{ item.sisa_qty.toLocaleString('id-ID') }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right" @click.stop>
                                            <span v-if="isManager" class="text-xs text-gray-400">Read-only</span>
                                            <button
                                                v-else
                                                :disabled="item.sisa_qty === 0"
                                                @click="openReject(item)"
                                                class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-semibold shadow-xs transition"
                                                :class="
                                                    item.sisa_qty === 0
                                                        ? 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-700'
                                                        : 'bg-red-600 text-white hover:bg-red-500 focus:outline-none focus:ring-2 focus:ring-red-500 dark:bg-red-500 dark:hover:bg-red-600'
                                                "
                                            >
                                                + Reject
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- ACCORDION DETAIL RINCIAN MATERIAL REJECT -->
                                    <tr v-if="expandedBatches[item.id]" class="bg-gray-50/90 dark:bg-gray-900/60">
                                        <td colspan="9" class="p-3 sm:p-4">
                                            <div class="rounded-xl border border-red-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                                <div class="mb-3 flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                            Rincian Komponen Material Reject Batch:
                                                            <span class="font-mono text-red-600 dark:text-red-400">{{ item.batch_number }}</span>
                                                        </span>
                                                        <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-semibold text-red-700 dark:bg-red-900/40 dark:text-red-300">
                                                            Total: {{ item.total_reject }} pcs
                                                        </span>
                                                    </div>
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                                        Produk Jadi: <strong>{{ item.produk?.nama_produk }}</strong> | Sisa: <strong :class="item.sisa_qty === 0 ? 'text-red-600' : 'text-green-600'">{{ item.sisa_qty }} pcs</strong>
                                                    </div>
                                                </div>

                                                <div v-if="!item.reject_details || item.reject_details.length === 0" class="py-5 text-center text-xs text-gray-500 dark:text-gray-400">
                                                    Belum ada item reject yang diinput pada batch ini. Tekan tombol <strong>+ Reject</strong> di atas untuk menambah data reject.
                                                </div>

                                                <div v-else class="overflow-x-auto">
                                                    <table class="min-w-[800px] w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                                        <thead class="bg-gray-50 dark:bg-gray-700/80">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Material / Komponen yang Di-Reject</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Jenis Reject</th>
                                                                <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Qty Reject</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Satuan (UoM)</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">No. MO / Dokumen Odoo</th>
                                                                <th class="px-3 py-2 text-left font-semibold text-gray-600 dark:text-gray-300">Waktu Input & Dicatat Oleh</th>
                                                                <th class="px-3 py-2 text-right font-semibold text-gray-600 dark:text-gray-300">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                                            <tr v-for="rd in item.reject_details" :key="rd.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                                                <td class="px-3 py-2">
                                                                    <div class="flex items-center gap-1.5 font-medium text-gray-900 dark:text-gray-100 flex-wrap">
                                                                        <span class="flex h-5 w-5 items-center justify-center rounded bg-gray-100 text-xs dark:bg-gray-700">
                                                                            {{ getItemCategory(rd.material_name).icon }}
                                                                        </span>
                                                                        <span class="font-bold">{{ rd.material_name || rd.keterangan || '-' }}</span>
                                                                        <span class="inline-flex items-center rounded-full px-1.5 py-0.2 text-[10px] font-semibold border" :class="getItemCategory(rd.material_name).class">
                                                                            {{ getItemCategory(rd.material_name).label }}
                                                                        </span>
                                                                        <button
                                                                            v-if="getItemCategory(rd.material_name).canDecompose"
                                                                            type="button"
                                                                            @click.stop="openRecipeBreakdown(rd)"
                                                                            class="inline-flex items-center gap-1 rounded bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-teal-700 hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition active:scale-95 shadow-xs"
                                                                            title="Lihat rincian takaran bahan baku penyusun yang terkandung di dalam produk reject ini"
                                                                        >
                                                                            🧪 Uraian Bahan Baku
                                                                        </button>
                                                                    </div>
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2">
                                                                    <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="getJenisBadge(rd.jenis_reject).class">
                                                                        {{ getJenisBadge(rd.jenis_reject).label }}
                                                                    </span>
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-right font-mono font-bold text-red-600 dark:text-red-400">
                                                                    {{ rd.jumlah.toLocaleString('id-ID') }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-gray-600 dark:text-gray-300 font-medium">
                                                                    {{ rd.material_uom || 'Pcs' }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 font-mono text-xs text-gray-700 dark:text-gray-300">
                                                                    <span v-if="rd.odoo_mo_name" class="rounded bg-amber-50 px-1.5 py-0.5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                                                        {{ rd.odoo_mo_name }}
                                                                    </span>
                                                                    <span v-else class="text-gray-400">-</span>
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-gray-500 dark:text-gray-400 text-[11px]">
                                                                    {{ new Date(rd.created_at).toLocaleString('id-ID', { dateStyle: 'short', timeStyle: 'short' }) }} · {{ rd.creator?.name || 'System / Odoo' }}
                                                                </td>
                                                                <td class="whitespace-nowrap px-3 py-2 text-right">
                                                                    <button
                                                                        v-if="canDeleteReject(rd)"
                                                                        type="button"
                                                                        @click.stop="openDeleteModal(rd)"
                                                                        class="text-red-600 hover:text-red-800 hover:underline dark:text-red-400"
                                                                    >
                                                                        Hapus
                                                                    </button>
                                                                    <span v-else class="text-gray-400">-</span>
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
                        <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-bold text-gray-900 dark:bg-gray-700 dark:text-gray-100">
                                        {{ item.batch_number }}
                                    </span>
                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[11px] font-medium capitalize text-slate-700 dark:bg-slate-700 dark:text-slate-200">
                                        {{ prosesLabel[item.proses] ?? item.proses }}
                                    </span>
                                </div>
                                <h3 class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100">
                                    {{ item.produk?.nama_produk ?? '-' }}
                                </h3>
                            </div>
                            <span class="text-[11px] font-medium text-gray-500 dark:text-gray-400">
                                {{ formatDate(item.tanggal) }}
                            </span>
                        </div>

                        <div class="my-2.5 grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40">
                                <span class="block text-[10px] text-gray-500 dark:text-gray-400">Tersedia</span>
                                <span class="font-bold text-gray-900 dark:text-gray-100">{{ item.available_qty.toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="rounded-lg bg-red-50/60 p-2 dark:bg-red-950/30">
                                <span class="block text-[10px] text-red-600 dark:text-red-400">Reject</span>
                                <span class="font-bold text-red-600 dark:text-red-400">{{ item.total_reject.toLocaleString('id-ID') }}</span>
                            </div>
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40">
                                <span class="block text-[10px] text-gray-500 dark:text-gray-400">Sisa</span>
                                <span class="font-bold" :class="item.sisa_qty === 0 ? 'text-red-600' : 'text-green-600'">{{ item.sisa_qty.toLocaleString('id-ID') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 border-t border-gray-100 pt-2.5 dark:border-gray-700">
                            <button
                                type="button"
                                @click="toggleBatchExpand(item.id)"
                                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-red-600 dark:text-gray-300 dark:hover:text-red-400"
                            >
                                <svg
                                    class="h-3.5 w-3.5 transition-transform"
                                    :class="{ 'rotate-90 text-red-600': expandedBatches[item.id] }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                                <span>Rincian Material ({{ item.reject_details?.length ?? 0 }})</span>
                            </button>

                            <button
                                v-if="!isManager"
                                :disabled="item.sisa_qty === 0"
                                @click="openReject(item)"
                                class="inline-flex items-center rounded-lg px-3 py-1 text-xs font-bold shadow-xs transition"
                                :class="
                                    item.sisa_qty === 0
                                        ? 'cursor-not-allowed bg-gray-200 text-gray-400 dark:bg-gray-700'
                                        : 'bg-red-600 text-white hover:bg-red-500 active:scale-95 dark:bg-red-500'
                                "
                            >
                                + Reject
                            </button>
                        </div>

                        <!-- Mobile Material Breakdown Cards -->
                        <div v-if="expandedBatches[item.id]" class="mt-3 border-t border-dashed border-gray-200 pt-3 dark:border-gray-700">
                            <div v-if="!item.reject_details || item.reject_details.length === 0" class="py-3 text-center text-xs text-gray-400">
                                Belum ada item reject pada batch ini.
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="rd in item.reject_details" :key="rd.id" class="rounded-lg bg-gray-50 p-2.5 text-xs dark:bg-gray-900/50 border border-gray-100 dark:border-gray-700">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="font-bold text-gray-900 dark:text-gray-100">{{ rd.material_name || rd.keterangan }}</span>
                                                <span class="inline-flex items-center rounded-full px-1.5 py-0.2 text-[9px] font-semibold border" :class="getItemCategory(rd.material_name).class">
                                                    {{ getItemCategory(rd.material_name).label }}
                                                </span>
                                            </div>
                                            <div class="mt-1 flex items-center gap-1.5 flex-wrap">
                                                <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="getJenisBadge(rd.jenis_reject).class">
                                                    {{ getJenisBadge(rd.jenis_reject).label }}
                                                </span>
                                                <span v-if="rd.odoo_mo_name" class="font-mono text-[10px] text-amber-700 dark:text-amber-300">
                                                    {{ rd.odoo_mo_name }}
                                                </span>
                                                <button
                                                    v-if="getItemCategory(rd.material_name).canDecompose"
                                                    type="button"
                                                    @click.stop="openRecipeBreakdown(rd)"
                                                    class="inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700 hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition"
                                                >
                                                    🧪 Uraian Bahan Baku
                                                </button>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <span class="font-mono font-bold text-red-600 dark:text-red-400 text-sm">
                                                {{ rd.jumlah.toLocaleString('id-ID') }}
                                            </span>
                                            <span class="text-[10px] text-gray-500 ml-0.5">{{ rd.material_uom || 'Pcs' }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex items-center justify-between text-[10px] text-gray-400 border-t border-gray-100 pt-1.5 dark:border-gray-800">
                                        <span>{{ new Date(rd.created_at).toLocaleDateString('id-ID') }}</span>
                                        <button v-if="canDeleteReject(rd)" @click.stop="openDeleteModal(rd)" class="text-red-600 font-semibold hover:underline">Hapus</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination for Batch View -->
                <div class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg">
                    <Pagination
                        :current-page="productions.current_page"
                        :last-page="productions.last_page"
                        :total="productions.total"
                        :per-page="productions.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- TAB 2: RINCIAN LENGKAP SEMUA MATERIAL REJECT (ITEMIZED VIEW)   -->
            <!-- ============================================================== -->
            <div v-show="activeTab === 'breakdown'">
                <!-- DESKTOP TABLE VIEW BREAKDOWN -->
                <div class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm min-w-[800px]">
                            <thead class="bg-gray-50 dark:bg-gray-700/70">
                                <tr>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Produk Jadi & Batch
                                    </th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Material / Komponen yang Di-Reject
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Jenis Reject
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Qty Reject
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Satuan (UoM)
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        No. MO / Dokumen Odoo
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Waktu & Sumber
                                    </th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">
                                        Aksi
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr v-if="!rejectDetails?.data || rejectDetails.data.length === 0">
                                    <td colspan="8" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data komponen reject yang sesuai filter.
                                    </td>
                                </tr>
                                <tr
                                    v-for="rd in rejectDetails?.data"
                                    :key="rd.id"
                                    class="hover:bg-red-50/30 dark:hover:bg-gray-700/50 transition"
                                >
                                    <td class="whitespace-nowrap px-3 py-2.5">
                                        <div class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ rd.laporan_harian?.produk?.nama_produk || 'Produk' }}
                                        </div>
                                        <div class="mt-0.5 flex items-center gap-1.5">
                                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] text-gray-800 dark:bg-gray-700 dark:text-gray-200">
                                                {{ rd.laporan_harian?.batch_number || '-' }}
                                            </span>
                                            <span class="text-[10px] text-gray-400 capitalize">
                                                {{ rd.laporan_harian?.proses || '' }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2.5">
                                        <div class="font-bold text-gray-900 dark:text-gray-100 flex items-center gap-1.5 flex-wrap">
                                            <span>{{ getItemCategory(rd.material_name).icon }}</span>
                                            <span>{{ rd.material_name || rd.keterangan || '-' }}</span>
                                            <span class="inline-flex items-center rounded-full px-1.5 py-0.2 text-[10px] font-semibold border" :class="getItemCategory(rd.material_name).class">
                                                {{ getItemCategory(rd.material_name).label }}
                                            </span>
                                            <button
                                                v-if="getItemCategory(rd.material_name).canDecompose"
                                                type="button"
                                                @click.stop="openRecipeBreakdown(rd)"
                                                class="inline-flex items-center gap-1 rounded bg-teal-50 px-2 py-0.5 text-[10px] font-bold text-teal-700 hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition active:scale-95 shadow-xs"
                                                title="Lihat rincian takaran bahan baku penyusun yang terkandung di dalam produk reject ini"
                                            >
                                                🧪 Uraian Bahan Baku
                                            </button>
                                        </div>
                                        <p v-if="rd.keterangan && rd.material_name && rd.keterangan !== rd.material_name" class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                                            {{ rd.keterangan }}
                                        </p>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="getJenisBadge(rd.jenis_reject).class">
                                            {{ getJenisBadge(rd.jenis_reject).label }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right font-mono font-bold text-red-600 dark:text-red-400 text-sm">
                                        {{ rd.jumlah.toLocaleString('id-ID') }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 font-semibold text-gray-700 dark:text-gray-300">
                                        {{ rd.material_uom || 'Pcs' }}
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 font-mono text-xs">
                                        <span v-if="rd.odoo_mo_name" class="rounded bg-amber-50 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                                            {{ rd.odoo_mo_name }}
                                        </span>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        <div>{{ new Date(rd.created_at).toLocaleDateString('id-ID') }}</div>
                                        <div class="text-[10px] text-gray-400">{{ rd.creator?.name || 'Odoo MO (DONE)' }}</div>
                                    </td>
                                    <td class="whitespace-nowrap px-3 py-2.5 text-right">
                                        <button
                                            v-if="canDeleteReject(rd)"
                                            type="button"
                                            @click.stop="openDeleteModal(rd)"
                                            class="text-red-600 hover:text-red-800 hover:underline dark:text-red-400 font-semibold"
                                        >
                                            Hapus
                                        </button>
                                        <span v-else class="text-gray-400">-</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- MOBILE CARD VIEW BREAKDOWN -->
                <div class="block space-y-3 md:hidden">
                    <div
                        v-if="!rejectDetails?.data || rejectDetails.data.length === 0"
                        class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400"
                    >
                        Tidak ada data komponen reject
                    </div>

                    <div
                        v-for="rd in rejectDetails?.data"
                        :key="rd.id"
                        class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                            <div>
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                        {{ rd.material_name || rd.keterangan }}
                                    </span>
                                    <span class="inline-flex items-center rounded-full px-1.5 py-0.2 text-[9px] font-semibold border" :class="getItemCategory(rd.material_name).class">
                                        {{ getItemCategory(rd.material_name).label }}
                                    </span>
                                    <button
                                        v-if="getItemCategory(rd.material_name).canDecompose"
                                        type="button"
                                        @click.stop="openRecipeBreakdown(rd)"
                                        class="inline-flex items-center gap-1 rounded bg-teal-50 px-1.5 py-0.5 text-[9px] font-bold text-teal-700 hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-300 border border-teal-200 dark:border-teal-800 transition"
                                    >
                                        🧪 Uraian Bahan Baku
                                    </button>
                                </div>
                                <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                    Produk Jadi: <strong>{{ rd.laporan_harian?.produk?.nama_produk }}</strong>
                                </div>
                            </div>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="getJenisBadge(rd.jenis_reject).class">
                                {{ getJenisBadge(rd.jenis_reject).label }}
                            </span>
                        </div>

                        <div class="my-2.5 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40">
                                <span class="block text-[10px] text-gray-500 dark:text-gray-400">Batch & MO</span>
                                <span class="font-mono font-bold text-gray-800 dark:text-gray-200">{{ rd.laporan_harian?.batch_number }}</span>
                                <div v-if="rd.odoo_mo_name" class="font-mono text-[10px] text-amber-700 dark:text-amber-300 mt-0.5">{{ rd.odoo_mo_name }}</div>
                            </div>
                            <div class="rounded-lg bg-red-50/60 p-2 text-right dark:bg-red-950/30">
                                <span class="block text-[10px] text-red-600 dark:text-red-400">Qty Reject</span>
                                <span class="font-bold text-red-600 dark:text-red-400 font-mono text-sm">
                                    {{ rd.jumlah.toLocaleString('id-ID') }}
                                </span>
                                <span class="text-[10px] text-gray-500 ml-0.5">{{ rd.material_uom || 'Pcs' }}</span>
                            </div>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-gray-400 border-t border-gray-100 pt-2 dark:border-gray-700">
                            <span>📅 {{ new Date(rd.created_at).toLocaleDateString('id-ID') }} · {{ rd.creator?.name || 'Odoo MO (DONE)' }}</span>
                            <button v-if="canDeleteReject(rd)" @click.stop="openDeleteModal(rd)" class="text-red-600 font-semibold hover:underline">Hapus</button>
                        </div>
                    </div>
                </div>

                <!-- Pagination for Breakdown View -->
                <div v-if="rejectDetails" class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg">
                    <Pagination
                        :current-page="rejectDetails.current_page"
                        :last-page="rejectDetails.last_page"
                        :total="rejectDetails.total"
                        :per-page="rejectDetails.per_page"
                        @page="goToRejectPage"
                    />
                </div>
            </div>

            <!-- ========================================== -->
            <!-- TAB 3: SCRAP ORDER ODOO ERP (LIVE)         -->
            <!-- ========================================== -->
            <div v-show="activeTab === 'odoo'">
                <div class="mb-4 flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex flex-1 flex-col items-stretch gap-2.5 sm:flex-row sm:items-center">
                        <div class="relative w-full sm:w-80">
                            <input
                                v-model="odooSearch"
                                type="text"
                                placeholder="Cari no scrap, no batch, produk..."
                                class="w-full rounded-md border-gray-300 pl-9 text-xs shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 sm:text-sm"
                            />
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                </svg>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-1.5 text-xs text-gray-600 dark:text-gray-400 sm:justify-start">
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
                            <svg class="h-4 w-4 text-red-600" :class="{ 'animate-spin': isLoadingOdooScraps }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                            {{ isLoadingOdooScraps ? 'Memuat...' : 'Muat Ulang dari Odoo' }}
                        </button>
                    </div>
                </div>

                <!-- Info Box -->
                <div class="mb-4 flex flex-col justify-between gap-1.5 rounded-lg border border-red-100 bg-red-50/50 p-3 text-xs text-red-800 dark:border-red-900/40 dark:bg-red-950/20 dark:text-red-300 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-2">
                        <span class="inline-block h-2 w-2 animate-pulse rounded-full bg-red-500"></span>
                        <span>💡 <em>Klik pada baris/kartu untuk membuka detail Scrap Order Odoo.</em></span>
                    </div>
                    <span v-if="filteredOdooScraps.length > 0" class="font-medium text-red-900 dark:text-red-200">
                        Total: {{ filteredOdooScraps.length }} Scrap Order
                    </span>
                </div>

                <!-- Error Message -->
                <div v-if="odooScrapError" class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/30 dark:text-red-300">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span>{{ odooScrapError }}</span>
                        </div>
                        <button @click="fetchOdooScraps" class="font-semibold underline hover:text-red-900 dark:hover:text-red-100">
                            Coba Lagi
                        </button>
                    </div>
                </div>

                <!-- DESKTOP TABLE VIEW ODOO -->
                <div class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm min-w-[700px]">
                            <thead class="bg-gray-50 dark:bg-gray-700/70">
                                <tr>
                                    <th class="w-8 px-2 py-2.5 text-center text-xs font-semibold uppercase text-gray-400"></th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">No. Dokumen</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">No. Batch</th>
                                    <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Produk / Komponen</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Qty Reject</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Tanggal</th>
                                    <th class="whitespace-nowrap px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800">
                                <tr v-if="isLoadingOdooScraps">
                                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <svg class="h-6 w-6 animate-spin text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            <span>Menghubungi Odoo ERP dan mengambil data Scrap...</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-else-if="paginatedOdooScraps.length === 0">
                                    <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        Tidak ada data Scrap Order di Odoo
                                    </td>
                                </tr>
                                <template v-for="scrap in paginatedOdooScraps" :key="scrap.id">
                                    <tr
                                        @click="toggleOdooScrapExpand(scrap.id)"
                                        class="cursor-pointer transition hover:bg-red-50/40 dark:hover:bg-gray-700/60"
                                        :class="{ 'bg-red-50/20 dark:bg-gray-700/30': expandedOdooScraps[scrap.id] }"
                                    >
                                        <td class="px-2 py-2.5 text-center text-gray-400">
                                            <svg
                                                class="h-4 w-4 text-gray-400 transition-transform duration-200"
                                                :class="{ 'rotate-90 text-red-600': expandedOdooScraps[scrap.id] }"
                                                fill="none"
                                                viewBox="0 0 24 24"
                                                stroke="currentColor"
                                            >
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                            </svg>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-red-600 dark:text-red-400">
                                            {{ scrap.name }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 font-mono font-bold text-gray-900 dark:text-gray-100">
                                            <span class="rounded bg-red-50 px-2 py-0.5 text-xs text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                                {{ scrap.batch_number }}
                                            </span>
                                        </td>
                                        <td class="px-3 py-2.5 font-medium text-gray-900 dark:text-gray-100">
                                            {{ scrap.product_name }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-right font-bold text-red-600 dark:text-red-400">
                                            {{ Number(scrap.scrap_qty).toLocaleString('id-ID') }} {{ scrap.uom }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-gray-600 dark:text-gray-300">
                                            {{ scrap.date ? formatDate(scrap.date) : '-' }}
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2.5 text-center">
                                            <span
                                                class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                                                :class="scrap.state === 'done' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300'"
                                            >
                                                {{ scrap.state }}
                                            </span>
                                        </td>
                                    </tr>

                                    <!-- ACCORDION DETAIL SCRAP ORDER -->
                                    <tr v-if="expandedOdooScraps[scrap.id]" class="bg-gray-50/90 dark:bg-gray-900/60">
                                        <td colspan="7" class="p-3 sm:p-4">
                                            <div class="rounded-xl border border-red-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                                                <div class="mb-3 flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-700">
                                                    <div class="flex items-center gap-2">
                                                        <span class="text-sm font-bold text-gray-900 dark:text-gray-100">
                                                            Detail Dokumen Odoo: <span class="font-mono text-red-600 dark:text-red-400">{{ scrap.name }}</span>
                                                        </span>
                                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold uppercase bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                            {{ scrap.state }}
                                                        </span>
                                                    </div>
                                                    <span class="text-xs text-gray-400">ID Record Odoo: #{{ scrap.id }}</span>
                                                </div>

                                                <div class="grid grid-cols-1 gap-4 text-xs sm:grid-cols-2 md:grid-cols-4">
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">🏷️ Nomor Batch (Lot / Serial)</p>
                                                        <p class="mt-1 font-mono text-sm font-bold text-red-600 dark:text-red-400">{{ scrap.batch_number }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">📦 Produk / Komponen</p>
                                                        <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ scrap.product_name }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">🔢 Jumlah Reject</p>
                                                        <p class="mt-1 text-sm font-bold text-red-600 dark:text-red-400">{{ Number(scrap.scrap_qty).toLocaleString('id-ID') }} {{ scrap.uom }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">📅 Tanggal Scrap</p>
                                                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ scrap.date ? formatDate(scrap.date) : '-' }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">📍 Lokasi Asal</p>
                                                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ scrap.location }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">🗑️ Lokasi Pemusnahan</p>
                                                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ scrap.scrap_location }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">👤 Dicatat Oleh</p>
                                                        <p class="mt-1 font-medium text-gray-900 dark:text-gray-100">{{ scrap.creator }}</p>
                                                    </div>
                                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-900/40">
                                                        <p class="font-medium text-gray-500 dark:text-gray-400">📝 Referensi Dokumen</p>
                                                        <p class="mt-1 font-mono text-gray-900 dark:text-gray-100">{{ scrap.origin }}</p>
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

                <!-- MOBILE VIEW ODOO -->
                <div class="block space-y-3 md:hidden">
                    <div v-if="paginatedOdooScraps.length === 0" class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                        Tidak ada data Scrap Order di Odoo.
                    </div>
                    <div v-for="scrap in paginatedOdooScraps" :key="scrap.id" class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                            <div>
                                <span class="font-mono text-sm font-bold text-red-600 dark:text-red-400">{{ scrap.name }}</span>
                                <h3 class="mt-0.5 text-sm font-bold text-gray-900 dark:text-gray-100">{{ scrap.product_name }}</h3>
                            </div>
                            <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                {{ scrap.state }}
                            </span>
                        </div>
                        <div class="my-2.5 grid grid-cols-2 gap-2 text-xs">
                            <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40">
                                <span class="block text-[10px] text-gray-500">Batch / Lot</span>
                                <span class="font-mono font-bold text-red-600">{{ scrap.batch_number }}</span>
                            </div>
                            <div class="rounded-lg bg-red-50/60 p-2 text-right dark:bg-red-950/30">
                                <span class="block text-[10px] text-red-600">Qty Reject</span>
                                <span class="font-bold text-red-600">{{ Number(scrap.scrap_qty).toLocaleString('id-ID') }} {{ scrap.uom }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pagination for Odoo Live Scraps -->
                <div v-if="filteredOdooScraps.length > 0" class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg">
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
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Form Reject Produk</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lengkapi data reject. Data produksi di bawah bersifat readonly.</p>

                <div v-if="selected" class="mt-4 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/30">
                    <dl class="grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400 text-xs">No. Batch</dt>
                            <dd class="mt-0.5 font-mono font-bold text-red-600 dark:text-red-400">{{ selected.batch_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400 text-xs">Nama Produk</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ selected.produk?.nama_produk }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400 text-xs">Process</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ prosesLabel[selected.proses] ?? selected.proses }}</dd>
                        </div>
                        <div>
                            <dt class="text-gray-500 dark:text-gray-400 text-xs">Qty Tersedia</dt>
                            <dd class="mt-0.5 font-medium text-gray-900 dark:text-gray-100">{{ selected.available_qty.toLocaleString('id-ID') }}</dd>
                        </div>
                    </dl>
                </div>

                <div class="mt-5 space-y-4">
                    <div>
                        <InputLabel value="Alasan / Keterangan Reject" />
                        <textarea
                            v-model="form.alasan_reject"
                            rows="3"
                            placeholder="Masukkan detail alasan reject (misal: botol pecah, segel rusak, cacat cetak)..."
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        ></textarea>
                        <InputError :message="errors.alasan_reject" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Qty Reject" />
                        <TextInput
                            v-model="form.qty_reject"
                            type="number"
                            min="1"
                            class="no-spinner mt-1 block w-full"
                            placeholder="0"
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Maksimal reject: {{ selected?.sisa_qty ?? 0 }} pcs
                        </p>
                        <InputError :message="errors.qty_reject" class="mt-1" />
                    </div>
                    <div>
                        <InputLabel value="Jenis Reject" />
                        <select
                            v-model="form.jenis_reject"
                            class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-red-500 focus:ring-red-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <option value="" disabled>Pilih Jenis Reject</option>
                            <option v-for="opt in jenisOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError :message="errors.jenis_reject" class="mt-1" />
                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton @click="closeModal">Batal</SecondaryButton>
                    <PrimaryButton @click="onSubmit" :disabled="isSubmitting">{{ isSubmitting ? 'Menyimpan...' : 'Simpan Reject' }}</PrimaryButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL KONFIRMASI SIMPAN REJECT -->
        <Modal :show="showConfirm" maxWidth="md" @close="showConfirm = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Konfirmasi Reject</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Apakah Anda yakin ingin menyimpan reject ini?</p>
                <div v-if="selected" class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-3 text-sm dark:border-gray-700 dark:bg-gray-900/30">
                    <p><span class="text-gray-500">No. Batch:</span> <span class="font-mono font-bold text-red-600">{{ selected.batch_number }}</span></p>
                    <p><span class="text-gray-500">Produk:</span> <span class="font-medium text-gray-900 dark:text-gray-100">{{ selected.produk?.nama_produk }}</span></p>
                    <p><span class="text-gray-500">Qty Reject:</span> <span class="font-bold text-red-600">{{ form.qty_reject }}</span></p>
                    <p><span class="text-gray-500">Jenis:</span> <span class="font-medium">{{ jenisLabel }}</span></p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton @click="showConfirm = false" :disabled="isSubmitting">Batal</SecondaryButton>
                    <DangerButton @click="confirmSubmit" :disabled="isSubmitting">{{ isSubmitting ? 'Menyimpan...' : 'Simpan Reject' }}</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- MODAL KONFIRMASI HAPUS REJECT -->
        <Modal :show="showDeleteConfirm" maxWidth="md" @close="showDeleteConfirm = false">
            <div class="p-6">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">Hapus Item Reject</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Apakah Anda yakin ingin menghapus catatan reject ini?</p>
                <div v-if="rejectToDelete" class="mt-4 rounded-md border border-red-200 bg-red-50/50 p-3 text-sm dark:border-red-900/40 dark:bg-red-950/20">
                    <p><span class="text-gray-500">Material:</span> <span class="font-bold text-gray-900 dark:text-gray-100">{{ rejectToDelete.material_name || rejectToDelete.keterangan }}</span></p>
                    <p><span class="text-gray-500">Jumlah:</span> <span class="font-bold text-red-600">{{ rejectToDelete.jumlah }} {{ rejectToDelete.material_uom || 'Pcs' }}</span></p>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <SecondaryButton @click="showDeleteConfirm = false" :disabled="isDeleting">Batal</SecondaryButton>
                    <DangerButton @click="confirmDeleteReject" :disabled="isDeleting">{{ isDeleting ? 'Menghapus...' : 'Ya, Hapus Data' }}</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- ============================================================== -->
        <!-- MODAL RINCIAN URAIAN BAHAN BAKU (FORMULA BoM ODOO)            -->
        <!-- ============================================================== -->
        <Modal :show="showRecipeModal" maxWidth="2xl" @close="showRecipeModal = false">
            <div class="p-6">
                <div class="flex items-start justify-between gap-3 border-b border-gray-100 pb-4 dark:border-gray-700">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-teal-100 text-teal-700 dark:bg-teal-950/60 dark:text-teal-300 text-lg shadow-sm">
                            🧪
                        </span>
                        <div>
                            <h2 class="text-base font-bold text-gray-900 dark:text-gray-100">
                                Uraian Komposisi Bahan Baku (Formula BoM Odoo)
                            </h2>
                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                Rincian takaran bahan baku yang terkandung dalam reject
                                <strong class="font-bold text-teal-700 dark:text-teal-300">{{ activeRecipeItem?.material_name }}</strong>
                                ({{ activeRecipeItem?.jumlah?.toLocaleString('id-ID') }} {{ activeRecipeItem?.material_uom || 'Pcs' }})
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        @click="showRecipeModal = false"
                        class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                    >
                        ✕
                    </button>
                </div>

                <!-- Loading State -->
                <div v-if="recipeLoading" class="py-12 text-center">
                    <div class="inline-block h-8 w-8 animate-spin rounded-full border-4 border-teal-500 border-t-transparent"></div>
                    <p class="mt-3 text-xs font-semibold text-gray-600 dark:text-gray-300">
                        Memuat formula komposisi bahan baku dari Odoo...
                    </p>
                </div>

                <!-- Error State -->
                <div v-else-if="recipeError" class="my-6 rounded-xl border border-red-200 bg-red-50/70 p-4 text-xs text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                    <div class="flex items-center gap-2 font-bold mb-1">
                        <span>⚠️</span>
                        <span>Informasi Formula</span>
                    </div>
                    <p>{{ recipeError }}</p>
                    <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">
                        Item ini dicatat langsung sebagai produk atau kemasan individual di Odoo.
                    </p>
                </div>

                <!-- Success State / Breakdown -->
                <div v-else-if="activeRecipe" class="mt-4 space-y-4">
                    <!-- Stat Highlights -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5">
                        <div class="rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                            <span class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">BoM Ruahan</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ activeRecipe.ruahan_bom_name }}</span>
                        </div>
                        <div class="rounded-xl border border-teal-100 bg-teal-50/60 p-3 dark:border-teal-900/40 dark:bg-teal-950/20">
                            <span class="block text-[10px] font-semibold text-teal-600 dark:text-teal-400 uppercase">Total Ruahan Terbuang</span>
                            <span class="text-xs font-bold text-teal-700 dark:text-teal-300">
                                {{ activeRecipe.total_batch_ruahan_kg >= 1 ? `${activeRecipe.total_batch_ruahan_kg.toLocaleString('id-ID')} Kg` : `${activeRecipe.total_batch_ruahan_g.toLocaleString('id-ID')} g` }}
                            </span>
                        </div>
                        <div class="col-span-2 sm:col-span-1 rounded-xl border border-gray-100 bg-gray-50/80 p-3 dark:border-gray-700/60 dark:bg-gray-900/40">
                            <span class="block text-[10px] font-semibold text-gray-500 dark:text-gray-400 uppercase">Dosis per Kemasan</span>
                            <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ activeRecipe.ruahan_dose_per_unit_g }} g / unit</span>
                        </div>
                    </div>

                    <!-- Table Raw Materials -->
                    <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="bg-gray-50 px-3.5 py-2 font-bold text-xs text-gray-700 dark:bg-gray-700/80 dark:text-gray-200 flex items-center justify-between">
                            <span>🧪 Daftar Bahan Baku (Raw Material) Terbuang</span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 font-normal">
                                {{ activeRecipe.raw_materials_count }} Bahan Baku
                            </span>
                        </div>
                        <div class="max-h-64 overflow-auto">
                            <table class="min-w-[450px] w-full divide-y divide-gray-200 text-xs dark:divide-gray-700">
                                <thead class="bg-gray-100/70 dark:bg-gray-800 text-[11px] text-gray-600 dark:text-gray-300">
                                    <tr>
                                        <th class="px-3 py-2 text-left font-semibold">Nama Bahan Baku</th>
                                        <th class="px-3 py-2 text-center font-semibold">Persentase (%)</th>
                                        <th class="px-3 py-2 text-right font-semibold">Dosis / Botol</th>
                                        <th class="px-3 py-2 text-right font-semibold">Total Terbuang</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/50">
                                    <tr
                                        v-for="(rm, idx) in activeRecipe.raw_materials"
                                        :key="idx"
                                        class="hover:bg-teal-50/30 dark:hover:bg-gray-700/30"
                                    >
                                        <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100">
                                            {{ rm.name }}
                                        </td>
                                        <td class="px-3 py-2 text-center text-gray-600 dark:text-gray-300 font-mono">
                                            {{ rm.percentage }}%
                                        </td>
                                        <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300 font-mono">
                                            {{ rm.qty_per_unit_g }} g
                                        </td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-teal-700 dark:text-teal-300">
                                            {{ rm.display_qty }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Primer Packaging (if any) -->
                    <div v-if="activeRecipe.primer_packaging && activeRecipe.primer_packaging.length > 0" class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                        <div class="bg-gray-50 px-3.5 py-2 font-bold text-xs text-gray-700 dark:bg-gray-700/80 dark:text-gray-200">
                            📦 Kemasan Primer Terkait
                        </div>
                        <div class="divide-y divide-gray-100 p-2.5 text-xs dark:divide-gray-700">
                            <div
                                v-for="(p, idx) in activeRecipe.primer_packaging"
                                :key="idx"
                                class="flex items-center justify-between py-1 px-1"
                            >
                                <span class="text-gray-800 dark:text-gray-200 font-medium">{{ p.name }}</span>
                                <span class="font-mono font-bold text-gray-900 dark:text-gray-100">
                                    {{ p.total_batch_qty }} {{ p.uom }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 flex justify-end">
                    <SecondaryButton @click="showRecipeModal = false">Tutup</SecondaryButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
