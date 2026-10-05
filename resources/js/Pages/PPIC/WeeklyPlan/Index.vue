<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import Board from './Board.vue';
import { type BoardPlan } from './components/BatchCard.vue';
import MoSyncButton from './components/MoSyncButton.vue';
import OdooMoSyncModal from '@/Components/OdooMoSyncModal.vue';

interface Produk {
    id: number;
    kode_produk?: string;
    nama_produk: string;
    proses_default?: string;
}

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
}

interface WeeklyPlan {
    id: number;
    produk_id: number;
    line_id?: number | null;
    proses: string;
    batch_number: string;
    odoo_mo_id?: number | null;
    mo_status?: string;
    target_output?: number;
    mp_count?: number;
    multiplier?: number;
    packing_hold?: boolean;
    tanggal: string;
    status: string;
    produk?: Produk;
    line?: Line | null;
    creator?: { id: number; name: string };
}

interface OdooBatchOption {
    batch_number: string;
    odoo_mo_id?: number | null;
    target_output?: number;
    mo_status?: string;
    proses?: string;
    tanggal?: string;
    status?: string;
}

const props = defineProps<{
    weeklyPlans: WeeklyPlan[];
    produks: Produk[];
    lines?: Line[];
    targetOutputMultiplier?: number;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const multiplier = computed(() => props.targetOutputMultiplier ?? 2000);
const activeTab = ref<'board' | 'table'>('board');
const syncing = ref(false);
const showMoSyncModal = ref(false);

const showCreate = ref(false);
const editItem = ref<WeeklyPlan | null>(null);
const deleteId = ref<number | null>(null);
const confirmDelete = ref(false);

// Date Helpers
function getMonday(d: Date): Date {
    const date = new Date(d);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    date.setDate(diff);
    date.setHours(0, 0, 0, 0);
    return date;
}

function formatDate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(d: Date, n: number): Date {
    const result = new Date(d);
    result.setDate(result.getDate() + n);
    return result;
}

function formatDisplayDate(dateStr: string): string {
    if (!dateStr) return '-';
    try {
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            return date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
            });
        }
    } catch {
        // fallback
    }
    return dateStr;
}

const currentWeekStart = ref(getMonday(new Date()));
const days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

const weekDates = computed(() => {
    return days.map((day, i) => ({
        day,
        date: formatDate(addDays(currentWeekStart.value, i)),
        dateLabel: addDays(currentWeekStart.value, i).toLocaleDateString(
            'id-ID',
            { day: 'numeric', month: 'short' },
        ),
    }));
});

const weekRangeLabel = computed(() => {
    const start = weekDates.value[0]?.dateLabel ?? '';
    const end = weekDates.value[5]?.dateLabel ?? '';
    const year = currentWeekStart.value.getFullYear();
    return `${start} — ${end} ${year}`;
});

function prevWeek() {
    currentWeekStart.value = addDays(currentWeekStart.value, -7);
}

function nextWeek() {
    currentWeekStart.value = addDays(currentWeekStart.value, 7);
}

function goToCurrentWeek() {
    currentWeekStart.value = getMonday(new Date());
}

function isCurrentWeek(dateStr: string): boolean {
    return weekDates.value.some((wd) => wd.date === dateStr);
}

const thisWeekPlansCount = computed(() => {
    const currentWeekDates = new Set(weekDates.value.map((wd) => wd.date));
    return props.weeklyPlans.filter((p) => currentWeekDates.has(p.tanggal)).length;
});

// Summary Stats
const summaryStats = computed(() => {
    const plans = props.weeklyPlans ?? [];
    return {
        total: plans.length,
        draft: plans.filter((p) => p.status === 'draft').length,
        aktif: plans.filter((p) => p.status === 'aktif').length,
        selesai: plans.filter((p) => p.status === 'selesai').length,
        hold: plans.filter((p) => p.packing_hold).length,
    };
});

// Grouped Odoo Batches by Product for auto-filling and suggestion
const odooBatchesByProduct = computed(() => {
    const map: Record<number, OdooBatchOption[]> = {};
    for (const plan of props.weeklyPlans) {
        if (!plan.produk_id || !plan.batch_number) continue;
        if (!map[plan.produk_id]) {
            map[plan.produk_id] = [];
        }
        if (!map[plan.produk_id].some((b) => b.batch_number === plan.batch_number && (b.proses || '') === (plan.proses || ''))) {
            map[plan.produk_id].push({
                batch_number: plan.batch_number,
                odoo_mo_id: plan.odoo_mo_id,
                target_output: plan.target_output,
                mo_status: plan.mo_status,
                proses: plan.proses,
                tanggal: plan.tanggal,
                status: plan.status,
            });
        }
    }
    return map;
});

const currentCreateProductBatches = computed(() => {
    const pId = Number(createForm.produk_id);
    if (!pId) return [];
    return odooBatchesByProduct.value[pId] ?? [];
});

const currentEditProductBatches = computed(() => {
    const pId = Number(editForm.produk_id);
    if (!pId) return [];
    return odooBatchesByProduct.value[pId] ?? [];
});

// Table Filtering & Search State
const searchQuery = ref('');
const statusFilter = ref<string>('all');
const prosesFilter = ref<string>('all');
const moStatusFilter = ref<string>('all');
const dateFilter = ref<string>('all');
const currentPage = ref(1);
const perPage = ref(15);

watch([searchQuery, statusFilter, prosesFilter, moStatusFilter, dateFilter, perPage], () => {
    currentPage.value = 1;
});

function resetFilters() {
    searchQuery.value = '';
    statusFilter.value = 'all';
    prosesFilter.value = 'all';
    moStatusFilter.value = 'all';
    dateFilter.value = 'all';
    currentPage.value = 1;
}

const hasActiveFilters = computed(() => {
    return (
        searchQuery.value.trim() !== '' ||
        statusFilter.value !== 'all' ||
        prosesFilter.value !== 'all' ||
        moStatusFilter.value !== 'all' ||
        dateFilter.value !== 'all'
    );
});

const filteredTablePlans = computed(() => {
    let result = [...props.weeklyPlans];
    const q = searchQuery.value.trim().toLowerCase();

    if (q) {
        result = result.filter((p) => {
            const batch = p.batch_number?.toLowerCase() || '';
            const prodName = p.produk?.nama_produk?.toLowerCase() || '';
            const prodCode = p.produk?.kode_produk?.toLowerCase() || '';
            const lineName = p.line?.nama_line?.toLowerCase() || '';
            const moId = p.odoo_mo_id ? String(p.odoo_mo_id) : '';
            return (
                batch.includes(q) ||
                prodName.includes(q) ||
                prodCode.includes(q) ||
                lineName.includes(q) ||
                moId.includes(q)
            );
        });
    }

    if (statusFilter.value !== 'all') {
        result = result.filter((p) => p.status === statusFilter.value);
    }

    if (prosesFilter.value !== 'all') {
        result = result.filter((p) => p.proses === prosesFilter.value);
    }

    if (moStatusFilter.value !== 'all') {
        result = result.filter((p) => (p.mo_status ?? 'pending') === moStatusFilter.value);
    }

    if (dateFilter.value === 'this_week') {
        const weekSet = new Set(weekDates.value.map((w) => w.date));
        result = result.filter((p) => weekSet.has(p.tanggal));
    } else if (dateFilter.value === 'today') {
        const todayStr = formatDate(new Date());
        result = result.filter((p) => p.tanggal === todayStr);
    }

    return result;
});

const totalPages = computed(() => {
    return Math.max(1, Math.ceil(filteredTablePlans.value.length / perPage.value));
});

const paginatedTablePlans = computed(() => {
    const start = (currentPage.value - 1) * perPage.value;
    return filteredTablePlans.value.slice(start, start + perPage.value);
});

const boardPlans = computed(() => props.weeklyPlans);

// Forms
const createForm = useForm({
    produk_id: '',
    line_id: '',
    proses: 'mixing',
    batch_number: '',
    tanggal: '',
    mp_count: '',
    odoo_mo_id: null as number | null,
    mo_status: '',
});

const editForm = useForm({
    produk_id: '',
    line_id: '',
    proses: 'mixing',
    batch_number: '',
    tanggal: '',
    mp_count: '',
});

function openCreate(date?: string) {
    createForm.reset();
    createForm.tanggal = date || formatDate(new Date());
    showCreate.value = true;
}

function openEdit(item: WeeklyPlan | BoardPlan) {
    editItem.value = item as WeeklyPlan;
    editForm.produk_id = String(item.produk_id);
    editForm.line_id = item.line_id ? String(item.line_id) : '';
    editForm.proses = item.proses;
    editForm.batch_number = item.batch_number;
    editForm.tanggal = item.tanggal;
    editForm.mp_count = item.mp_count != null ? String(item.mp_count) : '';
}

// Auto-fill logic when user chooses a product
function onProductChange() {
    const pId = Number(createForm.produk_id);
    if (!pId) return;

    const selectedProd = props.produks.find((p) => p.id === pId);
    if (selectedProd && selectedProd.proses_default) {
        createForm.proses = selectedProd.proses_default;
    }

    const batches = odooBatchesByProduct.value[pId] ?? [];
    if (batches.length > 0) {
        // Auto select the first batch from Odoo matching process or draft
        const chosen =
            batches.find((b) => b.status === 'draft' && b.proses === createForm.proses) ||
            batches.find((b) => b.status === 'draft') ||
            batches.find((b) => b.proses === createForm.proses) ||
            batches[0];
        selectCreateBatch(chosen);
    } else if (selectedProd) {
        // Fallback auto-format batch number
        const prefix = selectedProd.kode_produk ? selectedProd.kode_produk.replace(/[^a-zA-Z0-9]/g, '') : 'BATCH';
        const now = new Date();
        const dateTag = formatDate(now).replace(/-/g, '');
        createForm.batch_number = `${prefix}/${dateTag}/001`;
        createForm.odoo_mo_id = null;
        createForm.mo_status = 'pending';
    }
}

function selectCreateBatch(b: OdooBatchOption) {
    createForm.batch_number = b.batch_number;
    createForm.odoo_mo_id = b.odoo_mo_id ?? null;
    createForm.mo_status = b.mo_status ?? 'pending';
    if (b.proses) {
        createForm.proses = b.proses;
    }
    if (b.target_output && Number(b.target_output) > 0) {
        const recMp = Math.ceil(Number(b.target_output) / multiplier.value);
        createForm.mp_count = String(recMp > 0 ? recMp : 1);
    }
}

function selectEditBatch(b: OdooBatchOption) {
    editForm.batch_number = b.batch_number;
    if (b.proses) {
        editForm.proses = b.proses;
    }
    if (b.target_output && Number(b.target_output) > 0) {
        const recMp = Math.ceil(Number(b.target_output) / multiplier.value);
        editForm.mp_count = String(recMp > 0 ? recMp : 1);
    }
}

function setEditDateToday() {
    editForm.tanggal = formatDate(new Date());
}

function setEditDateCurrentWeek() {
    editForm.tanggal = formatDate(currentWeekStart.value);
}

function submitCreate() {
    createForm.post(route('ppic.weekly-plan.store'), {
        onSuccess: () => {
            showCreate.value = false;
            createForm.reset();
        },
    });
}

function submitEdit(andActivate = false) {
    if (!editItem.value) return;
    const currentId = editItem.value.id;
    const isDraft = editItem.value.status === 'draft';

    editForm.put(route('ppic.weekly-plan.update', currentId), {
        onSuccess: () => {
            if (andActivate && isDraft) {
                router.post(route('ppic.weekly-plan.activate', currentId), {}, {
                    onSuccess: () => {
                        editItem.value = null;
                    },
                });
            } else {
                editItem.value = null;
            }
        },
    });
}

function openDelete(id: number) {
    deleteId.value = id;
    confirmDelete.value = true;
}

function destroy() {
    if (deleteId.value) {
        router.delete(route('ppic.weekly-plan.destroy', deleteId.value), {
            onSuccess: () => {
                confirmDelete.value = false;
                deleteId.value = null;
            },
        });
    }
}

function activate(id: number) {
    router.post(route('ppic.weekly-plan.activate', id));
}

function toggleHold(plan: { id: number; packing_hold?: boolean }) {
    router.put(
        route('ppic.weekly-plan.update', plan.id),
        {
            packing_hold: !plan.packing_hold,
        },
        { preserveScroll: true },
    );
}

function syncOdooMo() {
    showMoSyncModal.value = true;
}

function handleMoSynced() {
    router.reload();
}

function targetPreview(
    mpCount: number | undefined | null,
    planMultiplier?: number | null,
): number {
    const mp = Number(mpCount) || 0;
    return mp * (planMultiplier || multiplier.value);
}

const prosesOptions = [
    { value: 'mixing', label: 'Mixing' },
    { value: 'filling', label: 'Filling' },
    { value: 'packing', label: 'Packing' },
];

const prosesColor: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800',
    filling: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
    packing: 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800',
};

const statusColor: Record<string, string> = {
    draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300 border border-amber-200 dark:border-amber-700/60',
    aktif: 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300 border border-green-200 dark:border-green-700/60',
    selesai: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700',
};

const moColor: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border border-gray-200 dark:border-gray-700',
    confirmed: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300 border border-sky-200 dark:border-sky-800',
    in_progress: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/50 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800',
    done: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800',
    cancelled: 'bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800',
};
</script>

<template>
    <Head title="Plan Produksi / WPS" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <div class="flex items-center gap-2">
                        <h2
                            class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100"
                        >
                            Plan Produksi / WPS
                        </h2>
                        <span
                            class="rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                        >
                            {{ summaryStats.total }} Total Batch
                        </span>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Perencanaan batch produksi mingguan oleh PPIC · Target =
                        MP × {{ multiplier }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <MoSyncButton :syncing="syncing" @sync="syncOdooMo" />
                    
                    <!-- Board / Table View Switcher -->
                    <div
                        class="inline-flex rounded-lg border border-gray-300 bg-gray-100/80 p-1 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-xs font-semibold transition"
                            :class="
                                activeTab === 'board'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                            "
                            @click="activeTab = 'board'"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" />
                            </svg>
                            Board (Kanban)
                        </button>
                        <button
                            type="button"
                            class="flex items-center gap-1.5 rounded-md px-3.5 py-1.5 text-xs font-semibold transition"
                            :class="
                                activeTab === 'table'
                                    ? 'bg-indigo-600 text-white shadow-sm'
                                    : 'text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white'
                            "
                            @click="activeTab = 'table'"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16" />
                            </svg>
                            Table (Semua Batch)
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <!-- Flash Message -->
            <div
                v-if="flash.success"
                class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-emerald-600 dark:text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ flash.success }}</span>
                </div>
            </div>
            <div
                v-if="flash.error"
                class="mb-4 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <div class="flex items-center gap-2">
                    <svg class="h-5 w-5 text-rose-600 dark:text-rose-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    <span>{{ flash.error }}</span>
                </div>
            </div>

            <!-- TAB 1: BOARD VIEW (KANBAN) -->
            <div v-if="activeTab === 'board'" class="space-y-4">
                <!-- Board Week Navigation Bar -->
                <div
                    class="flex flex-col gap-3 rounded-2xl border border-gray-200/80 bg-white p-4 shadow-sm dark:border-gray-700/80 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="flex items-center gap-2">
                        <SecondaryButton
                            @click="prevWeek"
                            class="py-1.5 text-xs font-semibold"
                        >
                            &larr; Minggu Lalu
                        </SecondaryButton>
                        <SecondaryButton
                            @click="goToCurrentWeek"
                            class="py-1.5 text-xs font-semibold"
                        >
                            Minggu Ini
                        </SecondaryButton>
                        <SecondaryButton
                            @click="nextWeek"
                            class="py-1.5 text-xs font-semibold"
                        >
                            Minggu Depan &rarr;
                        </SecondaryButton>
                    </div>

                    <div class="flex flex-col items-center sm:flex-row sm:gap-3">
                        <h3
                            class="text-sm font-bold tracking-tight text-gray-900 dark:text-gray-100 sm:text-base"
                        >
                            {{ weekRangeLabel }}
                        </h3>
                        <span
                            class="rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                        >
                            {{ thisWeekPlansCount }} batch terjadwal minggu ini
                        </span>
                    </div>

                    <div>
                        <PrimaryButton
                            type="button"
                            @click="openCreate()"
                            class="w-full justify-center text-xs sm:w-auto"
                        >
                            + Tambah Plan
                        </PrimaryButton>
                    </div>
                </div>

                <!-- Kanban Board Component -->
                <Board
                    :plans="boardPlans"
                    :multiplier="multiplier"
                    :startDate="currentWeekStart"
                    @activate="activate"
                    @toggle-hold="toggleHold"
                    @edit="openEdit"
                />
            </div>

            <!-- TAB 2: TABLE VIEW (ALL BATCHES & MAPPING) -->
            <div v-else class="space-y-4">
                <!-- Summary Stats Cards -->
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 lg:gap-4">
                    <div
                        class="flex flex-col rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total Semua Batch</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ summaryStats.total }}</span>
                            <span class="text-xs text-gray-400">Tersinkron Odoo</span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col rounded-xl border border-amber-200/80 bg-amber-50/50 p-3.5 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/20"
                    >
                        <span class="text-xs font-medium text-amber-800 dark:text-amber-400">Draft (Belum Aktif)</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ summaryStats.draft }}</span>
                            <span class="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-800 dark:bg-amber-900/60 dark:text-amber-300">Perlu Pemetaan</span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col rounded-xl border border-emerald-200/80 bg-emerald-50/50 p-3.5 shadow-sm dark:border-emerald-900/50 dark:bg-emerald-950/20"
                    >
                        <span class="text-xs font-medium text-emerald-800 dark:text-emerald-400">Aktif Berjalan</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold text-emerald-700 dark:text-emerald-300">{{ summaryStats.aktif }}</span>
                            <span class="rounded bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300">Masuk Board</span>
                        </div>
                    </div>

                    <div
                        class="flex flex-col rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                    >
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Selesai / Hold</span>
                        <div class="mt-1 flex items-baseline justify-between">
                            <span class="text-2xl font-bold text-gray-700 dark:text-gray-300">{{ summaryStats.selesai }} <span v-if="summaryStats.hold" class="text-sm font-normal text-amber-600">({{ summaryStats.hold }} hold)</span></span>
                            <span class="text-xs text-gray-400">Riwayat</span>
                        </div>
                    </div>
                </div>

                <!-- Info Banner on How to Map -->
                <div
                    class="flex items-start gap-3 rounded-xl border border-indigo-200 bg-gradient-to-r from-indigo-50/80 to-blue-50/80 p-3.5 text-xs text-indigo-950 shadow-sm dark:border-indigo-900/60 dark:from-indigo-950/30 dark:to-blue-950/30 dark:text-indigo-200"
                >
                    <span class="mt-0.5 text-base">💡</span>
                    <div class="leading-relaxed">
                        <strong class="font-semibold text-indigo-900 dark:text-indigo-200">Panduan Pemetaan Batch Odoo:</strong>
                        Cari batch yang ingin diproduksi minggu ini, klik <strong class="font-semibold underline">Edit</strong> untuk mengubah <em>Tanggal ke minggu berjalan</em>, memilih <em>Line Mesin</em>, mengisi <em>Jumlah MP</em>, lalu klik <strong class="font-semibold underline">Simpan & Aktifkan</strong>. Batch akan otomatis tampil di papan <strong>Board (Kanban)</strong>.
                    </div>
                </div>

                <!-- Search & Filters Toolbar -->
                <div
                    class="rounded-xl border border-gray-200 bg-white p-3.5 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2 lg:grid-cols-6">
                        <!-- Search Bar -->
                        <div class="lg:col-span-2">
                            <div class="relative">
                                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input
                                    v-model="searchQuery"
                                    type="text"
                                    placeholder="Cari Batch, Produk, Line, MO ID..."
                                    class="block w-full rounded-lg border-gray-300 pl-9 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                                />
                            </div>
                        </div>

                        <!-- Status Plan Filter -->
                        <div>
                            <select
                                v-model="statusFilter"
                                class="block w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="all">Semua Status Plan</option>
                                <option value="draft">Draft (Belum Aktif)</option>
                                <option value="aktif">Aktif Berjalan</option>
                                <option value="selesai">Selesai</option>
                            </select>
                        </div>

                        <!-- Proses Filter -->
                        <div>
                            <select
                                v-model="prosesFilter"
                                class="block w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="all">Semua Proses</option>
                                <option value="mixing">Mixing</option>
                                <option value="filling">Filling</option>
                                <option value="packing">Packing</option>
                            </select>
                        </div>

                        <!-- Status MO Filter -->
                        <div>
                            <select
                                v-model="moStatusFilter"
                                class="block w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="all">Semua Status MO</option>
                                <option value="pending">MO: Pending</option>
                                <option value="confirmed">MO: Confirmed</option>
                                <option value="in_progress">MO: In Progress</option>
                                <option value="done">MO: Done</option>
                                <option value="cancelled">MO: Cancelled</option>
                            </select>
                        </div>

                        <!-- Filter Waktu & Action Buttons -->
                        <div class="flex items-center gap-2">
                            <select
                                v-model="dateFilter"
                                class="block w-full rounded-lg border-gray-300 py-1.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="all">Semua Tanggal</option>
                                <option value="this_week">Minggu Ini</option>
                                <option value="today">Hari Ini</option>
                            </select>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                @click="resetFilters"
                                title="Reset Filter"
                                class="rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Reset
                            </button>
                            <PrimaryButton
                                type="button"
                                @click="openCreate()"
                                class="whitespace-nowrap text-xs"
                            >
                                + Plan
                            </PrimaryButton>
                        </div>
                    </div>
                </div>

                <!-- Full Data Table -->
                <div
                    class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <!-- Desktop/Tablet Table View -->
                    <div class="hidden md:block overflow-x-auto">
                        <table class="min-w-[850px] w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-700">
                            <thead class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3">Tanggal</th>
                                    <th class="px-4 py-3">Batch & MO</th>
                                    <th class="px-4 py-3">Produk</th>
                                    <th class="px-4 py-3">Proses</th>
                                    <th class="px-4 py-3">Line</th>
                                    <th class="px-4 py-3">MP & Target</th>
                                    <th class="px-4 py-3">Status Plan</th>
                                    <th class="px-4 py-3">Status MO</th>
                                    <th class="px-4 py-3 text-right">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-gray-700/60 dark:text-gray-200">
                                <tr
                                    v-if="paginatedTablePlans.length === 0"
                                    class="text-center"
                                >
                                    <td colspan="9" class="py-12 text-gray-400 dark:text-gray-500">
                                        <div class="flex flex-col items-center justify-center">
                                            <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p class="mt-2 text-sm font-semibold">Tidak ada batch plan yang ditemukan</p>
                                            <p class="text-xs text-gray-400">Coba ubah kata kunci pencarian atau reset filter.</p>
                                            <button
                                                v-if="hasActiveFilters"
                                                type="button"
                                                @click="resetFilters"
                                                class="mt-3 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                            >
                                                Reset Semua Filter
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr
                                    v-for="plan in paginatedTablePlans"
                                    :key="plan.id"
                                    class="transition hover:bg-gray-50/70 dark:hover:bg-gray-700/40"
                                >
                                    <!-- Tanggal -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ formatDisplayDate(plan.tanggal) }}
                                            </span>
                                            <span
                                                v-if="isCurrentWeek(plan.tanggal)"
                                                class="mt-0.5 inline-flex w-max rounded bg-indigo-50 px-1.5 py-0.2 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                            >
                                                Minggu Ini
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Batch & MO -->
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-xs font-bold text-gray-900 dark:text-gray-100">
                                                {{ plan.batch_number }}
                                            </span>
                                            <span
                                                v-if="plan.odoo_mo_id"
                                                class="font-mono text-[10px] text-gray-400 dark:text-gray-500"
                                            >
                                                MO #{{ plan.odoo_mo_id }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Produk -->
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                                {{ plan.produk?.nama_produk ?? '—' }}
                                            </span>
                                            <span class="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                                {{ plan.produk?.kode_produk }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Proses -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            :class="[
                                                'rounded-md px-2 py-0.5 text-[11px] font-bold capitalize',
                                                prosesColor[plan.proses] ?? 'bg-gray-100 text-gray-800',
                                            ]"
                                        >
                                            {{ plan.proses }}
                                        </span>
                                    </td>

                                    <!-- Line -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            v-if="plan.line"
                                            class="rounded-md bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{ plan.line.nama_line }}
                                        </span>
                                        <span
                                            v-else
                                            class="rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                        >
                                            Belum diatur
                                        </span>
                                    </td>

                                    <!-- MP & Target -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex flex-col">
                                            <span class="font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                                                {{
                                                    Number(
                                                        plan.target_output ||
                                                            targetPreview(
                                                                plan.mp_count,
                                                                plan.multiplier,
                                                            ),
                                                    ).toLocaleString('id-ID')
                                                }} pcs
                                            </span>
                                            <span class="text-[10px] text-gray-400">
                                                {{ plan.mp_count ?? 0 }} MP × {{ plan.multiplier || multiplier }}
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status Plan -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                :class="[
                                                    'inline-flex w-max rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                                    statusColor[plan.status] ?? 'bg-gray-100 text-gray-800',
                                                ]"
                                            >
                                                {{ plan.status }}
                                            </span>
                                            <span
                                                v-if="plan.packing_hold"
                                                class="inline-flex w-max rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold uppercase text-amber-800 dark:bg-amber-900/80 dark:text-amber-200"
                                            >
                                                Packing Hold
                                            </span>
                                        </div>
                                    </td>

                                    <!-- Status MO -->
                                    <td class="whitespace-nowrap px-4 py-3">
                                        <span
                                            :class="[
                                                'rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase',
                                                moColor[plan.mo_status ?? 'pending'] ?? 'bg-gray-100 text-gray-700',
                                            ]"
                                        >
                                            {{ plan.mo_status ?? 'pending' }}
                                        </span>
                                    </td>

                                    <!-- Aksi -->
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <!-- Edit Button -->
                                            <button
                                                type="button"
                                                class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 active:scale-95 dark:bg-indigo-950/60 dark:text-indigo-300 dark:hover:bg-indigo-900"
                                                @click="openEdit(plan)"
                                            >
                                                Edit
                                            </button>

                                            <!-- Aktifkan Button (for Draft) -->
                                            <button
                                                v-if="plan.status === 'draft'"
                                                type="button"
                                                class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:bg-emerald-950/60 dark:text-emerald-300 dark:hover:bg-emerald-900"
                                                @click="activate(plan.id)"
                                            >
                                                Aktifkan
                                            </button>

                                            <!-- Hold / Lepas Hold Button (for Aktif) -->
                                            <button
                                                v-if="plan.status === 'aktif'"
                                                type="button"
                                                class="rounded-md px-2 py-1 text-xs font-semibold transition"
                                                :class="
                                                    plan.packing_hold
                                                        ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900 dark:text-amber-200'
                                                        : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                                                "
                                                @click="toggleHold(plan)"
                                            >
                                                {{ plan.packing_hold ? 'Lepas Hold' : 'Hold' }}
                                            </button>

                                            <!-- Hapus Button (for Draft) -->
                                            <button
                                                v-if="plan.status === 'draft'"
                                                type="button"
                                                class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:bg-rose-950/60 dark:text-rose-300 dark:hover:bg-rose-900"
                                                @click="openDelete(plan.id)"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="block md:hidden divide-y divide-gray-100 dark:divide-gray-700/60">
                        <div
                            v-if="paginatedTablePlans.length === 0"
                            class="p-8 text-center text-gray-400 dark:text-gray-500"
                        >
                            <svg class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="mt-2 text-sm font-semibold">Tidak ada batch plan yang ditemukan</p>
                            <p class="text-xs text-gray-400">Coba ubah kata kunci pencarian atau reset filter.</p>
                            <button
                                v-if="hasActiveFilters"
                                type="button"
                                @click="resetFilters"
                                class="mt-3 text-xs font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                Reset Semua Filter
                            </button>
                        </div>

                        <div
                            v-for="plan in paginatedTablePlans"
                            :key="'mobile-' + plan.id"
                            class="p-4 space-y-3 transition hover:bg-gray-50/50 dark:hover:bg-gray-700/30"
                        >
                            <!-- Card Header: Date, Week badge, Status badges -->
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <span class="text-xs font-semibold text-gray-900 dark:text-gray-100">
                                        {{ formatDisplayDate(plan.tanggal) }}
                                    </span>
                                    <span
                                        v-if="isCurrentWeek(plan.tanggal)"
                                        class="ml-2 inline-flex rounded bg-indigo-50 px-1.5 py-0.2 text-[9px] font-semibold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                                    >
                                        Minggu Ini
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center justify-end gap-1">
                                    <span
                                        :class="[
                                            'inline-flex rounded-md px-2 py-0.5 text-[10px] font-bold uppercase',
                                            statusColor[plan.status] ?? 'bg-gray-100 text-gray-800',
                                        ]"
                                    >
                                        {{ plan.status }}
                                    </span>
                                    <span
                                        v-if="plan.packing_hold"
                                        class="inline-flex rounded bg-amber-100 px-1.5 py-0.2 text-[9px] font-bold uppercase text-amber-800 dark:bg-amber-900/80 dark:text-amber-200"
                                    >
                                        Hold
                                    </span>
                                </div>
                            </div>

                            <!-- Batch Number & Product -->
                            <div class="rounded-lg bg-gray-50 p-2.5 dark:bg-gray-900/50">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-xs font-bold text-gray-900 dark:text-gray-100">
                                        {{ plan.batch_number }}
                                    </span>
                                    <span
                                        v-if="plan.odoo_mo_id"
                                        class="font-mono text-[10px] text-gray-400 dark:text-gray-500"
                                    >
                                        MO #{{ plan.odoo_mo_id }}
                                    </span>
                                </div>
                                <div class="mt-1 text-xs font-semibold text-gray-900 dark:text-gray-100">
                                    {{ plan.produk?.nama_produk ?? '—' }}
                                </div>
                                <div class="font-mono text-[10px] text-gray-400 dark:text-gray-500">
                                    {{ plan.produk?.kode_produk }}
                                </div>
                            </div>

                            <!-- Process, Line, Target & MO status grid -->
                            <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex flex-col gap-1">
                                    <div class="text-[10px] text-gray-400">Proses & Line:</div>
                                    <div class="flex flex-wrap items-center gap-1">
                                        <span
                                            :class="[
                                                'rounded px-1.5 py-0.5 text-[10px] font-bold capitalize',
                                                prosesColor[plan.proses] ?? 'bg-gray-100 text-gray-800',
                                            ]"
                                        >
                                            {{ plan.proses }}
                                        </span>
                                        <span
                                            v-if="plan.line"
                                            class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                        >
                                            {{ plan.line.nama_line }}
                                        </span>
                                        <span
                                            v-else
                                            class="rounded bg-amber-50 px-1.5 py-0.5 text-[9px] font-medium text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                        >
                                            Belum diatur
                                        </span>
                                    </div>
                                </div>
                                <div class="flex flex-col gap-1">
                                    <div class="text-[10px] text-gray-400">Target Output:</div>
                                    <div class="font-mono font-bold text-gray-900 dark:text-gray-100">
                                        {{
                                            Number(
                                                plan.target_output ||
                                                    targetPreview(
                                                        plan.mp_count,
                                                        plan.multiplier,
                                                    ),
                                            ).toLocaleString('id-ID')
                                        }} pcs
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        {{ plan.mp_count ?? 0 }} MP | MO: <span class="uppercase font-semibold">{{ plan.mo_status ?? 'pending' }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex flex-wrap items-center justify-end gap-1.5 pt-2 border-t border-gray-100 dark:border-gray-700/60">
                                <button
                                    type="button"
                                    class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 active:scale-95 dark:bg-indigo-950/60 dark:text-indigo-300"
                                    @click="openEdit(plan)"
                                >
                                    Edit
                                </button>
                                <button
                                    v-if="plan.status === 'draft'"
                                    type="button"
                                    class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 active:scale-95 dark:bg-emerald-950/60 dark:text-emerald-300"
                                    @click="activate(plan.id)"
                                >
                                    Aktifkan
                                </button>
                                <button
                                    v-if="plan.status === 'aktif'"
                                    type="button"
                                    class="rounded-md px-2 py-1 text-xs font-semibold transition"
                                    :class="
                                        plan.packing_hold
                                            ? 'bg-amber-100 text-amber-800 hover:bg-amber-200 dark:bg-amber-900 dark:text-amber-200'
                                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-300'
                                    "
                                    @click="toggleHold(plan)"
                                >
                                    {{ plan.packing_hold ? 'Lepas Hold' : 'Hold' }}
                                </button>
                                <button
                                    v-if="plan.status === 'draft'"
                                    type="button"
                                    class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:bg-rose-950/60 dark:text-rose-300"
                                    @click="openDelete(plan.id)"
                                >
                                    Hapus
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Pagination Footer -->
                    <div
                        class="flex flex-col items-center justify-between gap-3 border-t border-gray-200 bg-gray-50/60 px-4 py-3 text-xs dark:border-gray-700 dark:bg-gray-900/40 sm:flex-row"
                    >
                        <div class="flex items-center gap-2 text-gray-600 dark:text-gray-400">
                            <span>Menampilkan</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">
                                {{ filteredTablePlans.length > 0 ? (currentPage - 1) * perPage + 1 : 0 }} -
                                {{ Math.min(currentPage * perPage, filteredTablePlans.length) }}
                            </span>
                            <span>dari</span>
                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ filteredTablePlans.length }}</span>
                            <span>batch</span>

                            <div class="ml-4 flex items-center gap-1.5">
                                <span>Per halaman:</span>
                                <select
                                    v-model="perPage"
                                    class="rounded border-gray-300 py-0.5 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                                >
                                    <option :value="15">15</option>
                                    <option :value="25">25</option>
                                    <option :value="50">50</option>
                                    <option :value="100">100</option>
                                </select>
                            </div>
                        </div>

                        <div class="flex items-center gap-1">
                            <button
                                type="button"
                                :disabled="currentPage <= 1"
                                @click="currentPage--"
                                class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                &larr; Prev
                            </button>
                            <span class="px-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                                Hal {{ currentPage }} dari {{ totalPages }}
                            </span>
                            <button
                                type="button"
                                :disabled="currentPage >= totalPages"
                                @click="currentPage++"
                                class="rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 disabled:opacity-40 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Next &rarr;
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreate" @close="showCreate = false">
            <form @submit.prevent="submitCreate" class="p-6">
                <h2
                    class="mb-4 text-lg font-bold text-gray-900 dark:text-gray-100"
                >
                    Tambah Weekly Plan
                </h2>
                <div class="mb-3">
                    <InputLabel value="Tanggal Plan" />
                    <TextInput
                        v-model="createForm.tanggal"
                        type="date"
                        class="mt-1 block w-full"
                        required
                    />
                    <InputError
                        :message="createForm.errors.tanggal"
                        class="mt-1"
                    />
                </div>
                <div class="mb-3">
                    <InputLabel value="Produk" />
                    <select
                        v-model="createForm.produk_id"
                        @change="onProductChange"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        required
                    >
                        <option value="" disabled>Pilih Produk</option>
                        <option v-for="p in produks" :key="p.id" :value="p.id">
                            {{ p.nama_produk }} ({{ p.kode_produk }})
                        </option>
                    </select>
                    <InputError
                        :message="createForm.errors.produk_id"
                        class="mt-1"
                    />
                </div>
                <div class="mb-3">
                    <InputLabel value="Line Mesin (opsional)" />
                    <select
                        v-model="createForm.line_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <option value="">— Tanpa Line / Belum Ditentukan —</option>
                        <option
                            v-for="l in lines ?? []"
                            :key="l.id"
                            :value="l.id"
                        >
                            {{ l.nama_line }}
                        </option>
                    </select>
                    <InputError
                        :message="createForm.errors.line_id"
                        class="mt-1"
                    />
                </div>
                <div class="mb-3">
                    <InputLabel value="Proses" />
                    <select
                        v-model="createForm.proses"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        required
                    >
                        <option
                            v-for="opt in prosesOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError
                        :message="createForm.errors.proses"
                        class="mt-1"
                    />
                </div>

                <!-- Batch Number Field with Auto-filled Odoo Batches -->
                <div class="mb-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Batch Number" />
                        <span
                            v-if="currentCreateProductBatches.length > 0"
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ currentCreateProductBatches.length }} Batch Odoo Tersedia
                        </span>
                    </div>
                    <TextInput
                        v-model="createForm.batch_number"
                        type="text"
                        list="odoo-create-batch-list"
                        class="mt-1 block w-full font-mono"
                        placeholder="Pilih produk atau ketik nomor batch..."
                        required
                    />
                    <datalist id="odoo-create-batch-list">
                        <option
                            v-for="b in currentCreateProductBatches"
                            :key="`${b.batch_number}-${b.proses}-${b.odoo_mo_id}`"
                            :value="b.batch_number"
                        >
                            {{ b.proses ? `[${b.proses.toUpperCase()}] ` : '' }}{{ b.odoo_mo_id ? `MO #${b.odoo_mo_id} (${b.mo_status ?? 'draft'})` : '' }}
                        </option>
                    </datalist>

                    <!-- Quick Batch Selector Pills if Odoo batches available -->
                    <div
                        v-if="currentCreateProductBatches.length > 0"
                        class="mt-2 space-y-1.5 rounded-lg border border-indigo-100 bg-indigo-50/50 p-2.5 dark:border-indigo-900/50 dark:bg-indigo-950/20"
                    >
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="flex items-center gap-1 font-semibold text-indigo-900 dark:text-indigo-300">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                </svg>
                                Pilih Batch Bawaan Odoo:
                            </span>
                            <span class="text-[10px] text-gray-500">Klik batch untuk mengisi & atur proses</span>
                        </div>
                        <div class="flex max-h-36 flex-wrap gap-1.5 overflow-y-auto pr-0.5">
                            <button
                                v-for="b in currentCreateProductBatches"
                                :key="`${b.batch_number}-${b.proses}-${b.odoo_mo_id}`"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-medium transition shadow-xs"
                                :class="
                                    createForm.batch_number === b.batch_number && (createForm.proses === b.proses || !b.proses)
                                        ? 'bg-indigo-600 font-bold text-white shadow-sm ring-1 ring-indigo-500'
                                        : 'border border-gray-200 bg-white text-gray-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/60'
                                "
                                @click="selectCreateBatch(b)"
                            >
                                <span class="font-mono font-bold">{{ b.batch_number }}</span>
                                <span
                                    v-if="b.proses"
                                    class="rounded px-1.5 py-0.2 text-[9px] font-extrabold uppercase tracking-wide"
                                    :class="
                                        createForm.batch_number === b.batch_number && (createForm.proses === b.proses || !b.proses)
                                            ? 'bg-white/20 text-white'
                                            : b.proses === 'mixing'
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200'
                                            : b.proses === 'filling'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200'
                                    "
                                >
                                    {{ b.proses }}
                                </span>
                                <span
                                    v-if="b.odoo_mo_id"
                                    class="font-sans text-[9px] opacity-75"
                                    :class="createForm.batch_number === b.batch_number && (createForm.proses === b.proses || !b.proses) ? 'text-indigo-100' : 'text-gray-400 dark:text-gray-500'"
                                >
                                    (MO #{{ b.odoo_mo_id }})
                                </span>
                            </button>
                        </div>
                    </div>

                    <InputError
                        :message="createForm.errors.batch_number"
                        class="mt-1"
                    />
                </div>

                <div class="mb-4">
                    <InputLabel value="Jumlah MP (Manpower)" />
                    <TextInput
                        v-model="createForm.mp_count"
                        type="number"
                        min="0"
                        class="mt-1 block w-full font-mono"
                        placeholder="0"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Target output otomatis:
                        <strong class="text-indigo-600 dark:text-indigo-400">{{ Number(createForm.mp_count || 0) * multiplier }} pcs</strong> (MP
                        × {{ multiplier }})
                    </p>
                    <InputError
                        :message="createForm.errors.mp_count"
                        class="mt-1"
                    />
                </div>
                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showCreate = false"
                        >Batal</SecondaryButton
                    >
                    <PrimaryButton :disabled="createForm.processing"
                        >Simpan Plan</PrimaryButton
                    >
                </div>
            </form>
        </Modal>

        <!-- Edit Modal -->
        <Modal :show="!!editItem" @close="editItem = null">
            <form v-if="editItem" @submit.prevent="submitEdit(false)" class="p-6">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2
                            class="text-lg font-bold text-gray-900 dark:text-gray-100"
                        >
                            Edit Weekly Plan
                        </h2>
                        <p class="font-mono text-xs text-gray-500 dark:text-gray-400">
                            Batch: {{ editItem.batch_number }}
                        </p>
                    </div>
                    <span
                        :class="[
                            'rounded-md px-2.5 py-0.5 text-xs font-bold uppercase',
                            statusColor[editItem.status] ?? 'bg-gray-100 text-gray-800',
                        ]"
                    >
                        {{ editItem.status }}
                    </span>
                </div>

                <div
                    v-if="editItem.status === 'aktif'"
                    class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    Plan aktif: edit terbatas ke MP, Line, Packing Hold{{
                        editItem.proses === 'packing'
                            ? ', dan tanggal (jika hold)'
                            : ''
                    }}. Produk/proses/batch dikunci.
                </div>

                <!-- Tanggal Field with Quick Week Selector -->
                <div class="mb-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Tanggal Plan" />
                        <div class="flex items-center gap-1.5">
                            <button
                                type="button"
                                @click="setEditDateToday"
                                class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                + Set Hari Ini
                            </button>
                            <span class="text-gray-300">·</span>
                            <button
                                type="button"
                                @click="setEditDateCurrentWeek"
                                class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                            >
                                + Set Awal Minggu
                            </button>
                        </div>
                    </div>
                    <TextInput
                        v-model="editForm.tanggal"
                        type="date"
                        class="mt-1 block w-full"
                        :required="
                            editItem.status === 'draft' ||
                            (editItem.status === 'aktif' &&
                                editItem.proses === 'packing')
                        "
                        :disabled="
                            editItem.status === 'aktif' &&
                            editItem.proses !== 'packing'
                        "
                    />
                    <InputError
                        :message="editForm.errors.tanggal"
                        class="mt-1"
                    />
                </div>

                <!-- Produk Field -->
                <div class="mb-3">
                    <InputLabel value="Produk" />
                    <select
                        v-model="editForm.produk_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        :disabled="editItem.status === 'aktif'"
                        :required="editItem.status === 'draft'"
                    >
                        <option v-for="p in produks" :key="p.id" :value="p.id">
                            {{ p.nama_produk }} ({{ p.kode_produk }})
                        </option>
                    </select>
                    <InputError
                        :message="editForm.errors.produk_id"
                        class="mt-1"
                    />
                </div>

                <!-- Line Mesin Field -->
                <div class="mb-3">
                    <InputLabel value="Line Mesin" />
                    <select
                        v-model="editForm.line_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                    >
                        <option value="">— Tanpa Line / Belum Ditentukan —</option>
                        <option
                            v-for="l in lines ?? []"
                            :key="l.id"
                            :value="l.id"
                        >
                            {{ l.nama_line }}
                        </option>
                    </select>
                    <InputError
                        :message="editForm.errors.line_id"
                        class="mt-1"
                    />
                </div>

                <!-- Proses Field -->
                <div class="mb-3">
                    <InputLabel value="Proses" />
                    <select
                        v-model="editForm.proses"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        :disabled="editItem.status === 'aktif'"
                        :required="editItem.status === 'draft'"
                    >
                        <option
                            v-for="opt in prosesOptions"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                    <InputError
                        :message="editForm.errors.proses"
                        class="mt-1"
                    />
                </div>

                <!-- Batch Number Field -->
                <div class="mb-3">
                    <div class="flex items-center justify-between">
                        <InputLabel value="Batch Number" />
                        <span
                            v-if="editItem.status === 'draft' && currentEditProductBatches.length > 0"
                            class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                        >
                            {{ currentEditProductBatches.length }} Batch Odoo
                        </span>
                    </div>
                    <TextInput
                        v-model="editForm.batch_number"
                        type="text"
                        list="odoo-edit-batch-list"
                        class="mt-1 block w-full font-mono"
                        :disabled="editItem.status === 'aktif'"
                        :required="editItem.status === 'draft'"
                    />
                    <datalist v-if="editItem.status === 'draft'" id="odoo-edit-batch-list">
                        <option
                            v-for="b in currentEditProductBatches"
                            :key="`${b.batch_number}-${b.proses}-${b.odoo_mo_id}`"
                            :value="b.batch_number"
                        >
                            {{ b.proses ? `[${b.proses.toUpperCase()}] ` : '' }}{{ b.odoo_mo_id ? `MO #${b.odoo_mo_id}` : '' }}
                        </option>
                    </datalist>

                    <!-- Quick Batch Selector for Draft Edit -->
                    <div
                        v-if="editItem.status === 'draft' && currentEditProductBatches.length > 0"
                        class="mt-2 space-y-1.5 rounded-lg border border-indigo-100 bg-indigo-50/50 p-2.5 dark:border-indigo-900/50 dark:bg-indigo-950/20"
                    >
                        <div class="flex items-center justify-between text-[11px]">
                            <span class="font-semibold text-indigo-900 dark:text-indigo-300">
                                Pilih Batch Odoo:
                            </span>
                            <span class="text-[10px] text-gray-500">Klik untuk ganti batch & proses</span>
                        </div>
                        <div class="flex max-h-36 flex-wrap gap-1.5 overflow-y-auto pr-0.5">
                            <button
                                v-for="b in currentEditProductBatches"
                                :key="`${b.batch_number}-${b.proses}-${b.odoo_mo_id}`"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-medium transition shadow-xs"
                                :class="
                                    editForm.batch_number === b.batch_number && (editForm.proses === b.proses || !b.proses)
                                        ? 'bg-indigo-600 font-bold text-white shadow-sm ring-1 ring-indigo-500'
                                        : 'border border-gray-200 bg-white text-gray-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/60'
                                "
                                @click="selectEditBatch(b)"
                            >
                                <span class="font-mono font-bold">{{ b.batch_number }}</span>
                                <span
                                    v-if="b.proses"
                                    class="rounded px-1.5 py-0.2 text-[9px] font-extrabold uppercase tracking-wide"
                                    :class="
                                        editForm.batch_number === b.batch_number && (editForm.proses === b.proses || !b.proses)
                                            ? 'bg-white/20 text-white'
                                            : b.proses === 'mixing'
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200'
                                            : b.proses === 'filling'
                                            ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                            : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200'
                                    "
                                >
                                    {{ b.proses }}
                                </span>
                                <span
                                    v-if="b.odoo_mo_id"
                                    class="font-sans text-[9px] opacity-75"
                                    :class="editForm.batch_number === b.batch_number && (editForm.proses === b.proses || !b.proses) ? 'text-indigo-100' : 'text-gray-400 dark:text-gray-500'"
                                >
                                    (MO #{{ b.odoo_mo_id }})
                                </span>
                            </button>
                        </div>
                    </div>

                    <InputError
                        :message="editForm.errors.batch_number"
                        class="mt-1"
                    />
                </div>

                <!-- Manpower Field -->
                <div class="mb-4">
                    <InputLabel value="Jumlah MP (Manpower)" />
                    <TextInput
                        v-model="editForm.mp_count"
                        type="number"
                        min="0"
                        class="mt-1 block w-full font-mono"
                    />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Target output:
                        <strong class="text-indigo-600 dark:text-indigo-400">
                            {{
                                Number(editForm.mp_count || 0) *
                                (editItem.multiplier || multiplier)
                            }} pcs
                        </strong>
                    </p>
                    <InputError
                        :message="editForm.errors.mp_count"
                        class="mt-1"
                    />
                </div>

                <!-- Modal Actions -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                    <div>
                        <SecondaryButton type="button" @click="editItem = null">
                            Batal
                        </SecondaryButton>
                    </div>
                    <div class="flex items-center gap-2">
                        <PrimaryButton
                            type="button"
                            :disabled="editForm.processing"
                            @click="submitEdit(false)"
                        >
                            Simpan Perubahan
                        </PrimaryButton>
                        <button
                            v-if="editItem.status === 'draft'"
                            type="button"
                            :disabled="editForm.processing"
                            @click="submitEdit(true)"
                            class="inline-flex items-center rounded-md border border-transparent bg-emerald-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition hover:bg-emerald-500 focus:bg-emerald-700 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 active:bg-emerald-900 disabled:opacity-50"
                        >
                            Simpan & Aktifkan
                        </button>
                    </div>
                </div>
            </form>
        </Modal>

        <!-- Delete Modal -->
        <Modal :show="confirmDelete" @close="confirmDelete = false">
            <div class="p-6">
                <h2
                    class="text-lg font-bold text-gray-900 dark:text-gray-100"
                >
                    Konfirmasi Hapus Plan
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus weekly plan draft ini? Tindakan ini tidak dapat dibatalkan.
                </p>
                <div class="mt-5 flex justify-end gap-2">
                    <SecondaryButton @click="confirmDelete = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="destroy">Hapus Plan</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- Odoo MO Preview & Selective Sync Modal -->
        <OdooMoSyncModal
            :show="showMoSyncModal"
            @close="showMoSyncModal = false"
            @synced="handleMoSynced"
        />
    </AuthenticatedLayout>
</template>
