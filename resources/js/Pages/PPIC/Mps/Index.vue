<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import Modal from '@/Components/Modal.vue';

interface Produk {
    id: number;
    kode_produk: string;
    nama_produk: string;
    item_type?: string;
    work_center_id?: number | null;
}

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: 'mixing' | 'filling' | 'secondary';
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
}

interface MpsPlan {
    id: number;
    produk_id: number;
    work_center_id: number;
    batch_number?: string | null;
    month_year: string;
    revision: number;
    is_active_revision: boolean;
    status: string;
    notes: string | null;
    created_by: number;
    produk: Produk;
    work_center?: WorkCenter;
    workCenter?: WorkCenter;
    creator: { id: number; name: string };
    items_count: number;
    items?: any[];
}

interface OdooBatch {
    id: number;
    produk_id: number;
    batch_number: string;
    proses?: string;
    odoo_mo_id?: number | null;
    mo_status?: string;
    target_output?: number;
}

const props = defineProps<{
    plans: {
        data: MpsPlan[];
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
    monthYear: string;
    status: string;
    produks: Produk[];
    workCenters: WorkCenter[];
    odooBatches?: OdooBatch[];
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});

const monthYearInput = ref(props.monthYear);
const statusFilter = ref(props.status);

const showCreate = ref(false);
const deleteId = ref<number | null>(null);

function getWorkCenter(plan: MpsPlan): WorkCenter | undefined {
    return plan.work_center ?? plan.workCenter;
}

function getProcessBadgeClass(type?: string): string {
    if (type === 'mixing') {
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300';
    }
    if (type === 'filling') {
        return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300';
    }
    if (type === 'secondary') {
        return 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300';
    }
    return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
}

function formatMonthYear(ym: string): string {
    const [y, m] = ym.split('-');
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    return `${months[parseInt(m) - 1]} ${y}`;
}

function getStatusColor(status: string): string {
    const colors: Record<string, string> = {
        draft: 'bg-amber-100 text-amber-800 dark:bg-amber-900/50 dark:text-amber-300',
        approved: 'bg-sky-100 text-sky-800 dark:bg-sky-900/50 dark:text-sky-300',
        active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300',
        closed: 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300',
    };
    return colors[status] ?? 'bg-gray-100 text-gray-800';
}

const createForm = useForm({
    produk_id: '',
    work_center_id: '',
    batch_number: '',
    month_year: props.monthYear,
    notes: '',
});

const statusOptions = [
    { value: 'all', label: 'Semua Status' },
    { value: 'draft', label: 'Draft' },
    { value: 'approved', label: 'Approved' },
    { value: 'active', label: 'Active' },
    { value: 'closed', label: 'Closed' },
];

function applyFilters() {
    router.get(route('ppic.mps.index'), {
        month_year: monthYearInput.value,
        status: statusFilter.value,
    }, { preserveScroll: true });
}

watch([monthYearInput, statusFilter], () => {
    applyFilters();
});

const produkSearch = ref('');
const produkDropdownOpen = ref(false);

const filteredProduks = computed(() => {
    const q = produkSearch.value.trim().toLowerCase();
    const list = q
        ? props.produks.filter(
              (p) =>
                  p.nama_produk.toLowerCase().includes(q) ||
                  (p.kode_produk ?? '').toLowerCase().includes(q),
          )
        : props.produks;

    return [...list].sort((a, b) =>
        a.nama_produk.localeCompare(b.nama_produk, 'id'),
    );
});

const selectedProduk = computed(() =>
    props.produks.find((p) => p.id === Number(createForm.produk_id)),
);

const currentCreateProductBatches = computed(() => {
    const pId = Number(createForm.produk_id);
    if (!pId || !props.odooBatches) return [];

    const list: OdooBatch[] = [];
    const seen = new Set<string>();
    for (const b of props.odooBatches) {
        if (b.produk_id === pId && b.batch_number) {
            const key = `${b.batch_number}-${b.proses ?? ''}`;
            if (!seen.has(key)) {
                seen.add(key);
                list.push(b);
            }
        }
    }
    return list;
});

function selectBatch(b: OdooBatch) {
    createForm.batch_number = b.batch_number;
    createForm.clearErrors('batch_number');
    if (b.proses) {
        const targetType = b.proses === 'packing' ? 'secondary' : b.proses;
        const wc = props.workCenters.find(w => (w.type === targetType || w.type === b.proses) && w.is_active);
        if (wc) {
            createForm.work_center_id = String(wc.id);
        }
    }
}

function toggleProdukDropdown() {
    produkDropdownOpen.value = !produkDropdownOpen.value;
    if (produkDropdownOpen.value) {
        produkSearch.value = '';
    }
}

function chooseProduk(p: Produk) {
    createForm.produk_id = String(p.id);
    createForm.clearErrors('produk_id');
    produkDropdownOpen.value = false;
    produkSearch.value = '';

    // Set work center from product default if available
    if (p.work_center_id) {
        createForm.work_center_id = String(p.work_center_id);
    }

    // Batches from Odoo for this product
    const batches = (props.odooBatches ?? []).filter(
        (b) => b.produk_id === p.id && b.batch_number,
    );

    if (batches.length > 0) {
        // Auto select the first batch from Odoo (sama seperti di Tambah Weekly Plan)
        selectBatch(batches[0]);
    } else {
        // Fallback auto-format batch number jika produk belum ada batch Odoo
        const prefix = p.kode_produk ? p.kode_produk.replace(/[^a-zA-Z0-9]/g, '') : 'PRD';
        const now = new Date();
        const dateTag = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`;
        createForm.batch_number = `${prefix}/${dateTag}/001`;
        createForm.clearErrors('batch_number');
    }
}

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    createForm.month_year = props.monthYear;
    createForm.batch_number = '';
    produkSearch.value = '';
    produkDropdownOpen.value = false;
    showCreate.value = true;
}

function closeCreate() {
    showCreate.value = false;
    produkDropdownOpen.value = false;
    produkSearch.value = '';
}

function submitCreate() {
    if (!createForm.produk_id) {
        createForm.setError('produk_id', 'Silakan pilih produk terlebih dahulu.');
        return;
    }
    createForm.post(route('ppic.mps.store'), {
        onSuccess: () => {
            closeCreate();
        },
    });
}

function openDelete(id: number) {
    deleteId.value = id;
}

function destroy() {
    if (deleteId.value) {
        router.delete(route('ppic.mps.destroy', deleteId.value), {
            onSuccess: () => {
                deleteId.value = null;
            },
        });
    }
}

function getStatusLabel(status: string): string {
    const labels: Record<string, string> = {
        draft: 'Draft',
        approved: 'Approved',
        active: 'Active',
        closed: 'Closed',
    };
    return labels[status] ?? status;
}
</script>

<template>
    <Head title="MPS - Master Production Schedule" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        Master Production Schedule (MPS)
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Perencanaan produksi bulanan per Produk & Work Center
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <PrimaryButton @click="openCreate">
                        + Buat MPS Plan
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <div v-if="flash.success" class="mb-4 flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-200">
                {{ flash.error }}
            </div>

            <!-- Filters -->
            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Bulan & Tahun" />
                        <input
                            v-model="monthYearInput"
                            type="month"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </div>
                    <div>
                        <InputLabel value="Status" />
                        <select
                            v-model="statusFilter"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 py-1.5 text-xs shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        >
                            <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">
                                {{ opt.label }}
                            </option>
                        </select>
                    </div>
                    <div class="flex items-end">
                        <PrimaryButton @click="applyFilters" class="w-full justify-center">
                            Terapkan Filter
                        </PrimaryButton>
                    </div>
                </div>
            </div>

            <!-- Plans Table -->
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Bulan</th>
                                <th class="px-4 py-3">Produk</th>
                                <th class="px-4 py-3">Nomor Batch</th>
                                <th class="px-4 py-3">Work Center</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Items</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-if="props.plans.data.length === 0" class="text-center">
                                <td colspan="7" class="py-12 text-gray-400 dark:text-gray-500">
                                    <div class="flex flex-col items-center justify-center">
                                        <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                        </svg>
                                        <p class="mt-2 text-sm font-semibold">Belum ada MPS Plan</p>
                                        <p class="text-xs text-gray-400">Klik "Buat MPS Plan" untuk memulai</p>
                                    </div>
                                </td>
                            </tr>
                            <tr
                                v-for="plan in props.plans.data"
                                :key="plan.id"
                                class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40"
                            >
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ formatMonthYear(plan.month_year) }}</span>
                                        <span class="rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-bold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                                            R{{ plan.revision ?? 1 }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ plan.produk.nama_produk }}
                                        </span>
                                        <span class="font-mono text-[11px] text-gray-400 dark:text-gray-500">
                                            {{ plan.produk.kode_produk }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="font-mono text-xs font-bold text-gray-900 dark:text-gray-100">
                                            {{ plan.batch_number || '—' }}
                                        </span>
                                        <span
                                            v-if="getWorkCenter(plan)?.type"
                                            class="rounded px-1.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wide"
                                            :class="getProcessBadgeClass(getWorkCenter(plan)?.type)"
                                        >
                                            {{ getWorkCenter(plan)?.type === 'secondary' ? 'packaging' : getWorkCenter(plan)?.type }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span
                                        v-if="getWorkCenter(plan)"
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': getWorkCenter(plan)?.type === 'mixing',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300': getWorkCenter(plan)?.type === 'filling',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': getWorkCenter(plan)?.type === 'secondary',
                                        }"
                                    >
                                        {{ getWorkCenter(plan)?.code }} - {{ getWorkCenter(plan)?.name }}
                                    </span>
                                    <span v-else class="text-xs text-gray-400">—</span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span :class="['inline-flex w-max rounded-md px-2 py-0.5 text-[10px] font-bold uppercase', getStatusColor(plan.status)]">
                                        {{ getStatusLabel(plan.status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ plan.items_count ?? plan.items?.length ?? 0 }}
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <Link
                                            :href="route('ppic.mps.show', plan.id)"
                                            class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 transition hover:bg-indigo-100 active:scale-95 dark:bg-indigo-950/60 dark:text-indigo-300"
                                        >
                                            Grid
                                        </Link>
                                        <button
                                            v-if="plan.status === 'draft'"
                                            type="button"
                                            class="rounded-md bg-rose-50 px-2 py-1 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 active:scale-95 dark:bg-rose-950/60 dark:text-rose-300"
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

                <div class="px-4 py-3 border-t border-gray-200 dark:border-gray-700">
                    <nav class="flex items-center justify-between" aria-label="Pagination">
                        <div class="text-xs text-gray-500 dark:text-gray-400">
                            Menampilkan {{ (props.plans.current_page - 1) * props.plans.per_page + 1 }} sampai
                            {{ Math.min(props.plans.current_page * props.plans.per_page, props.plans.total) }}
                            dari {{ props.plans.total }}
                        </div>
                        <div class="flex gap-1">
                            <button
                                v-if="props.plans.current_page > 1"
                                @click="router.get(route('ppic.mps.index'), { page: props.plans.current_page - 1, month_year: monthYearInput, status: statusFilter }, { preserveScroll: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Sebelumnya
                            </button>
                            <button
                                v-if="props.plans.current_page < props.plans.last_page"
                                @click="router.get(route('ppic.mps.index'), { page: props.plans.current_page + 1, month_year: monthYearInput, status: statusFilter }, { preserveScroll: true })"
                                class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
                            >
                                Selanjutnya
                            </button>
                        </div>
                    </nav>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <Modal :show="showCreate" @close="closeCreate">
            <template #title>Buat MPS Plan Baru</template>
            <template #content>
                <form id="create-form" @submit.prevent="submitCreate" class="space-y-4">
                    <div class="mb-3">
                        <div class="flex items-center justify-between">
                            <InputLabel value="Produk Finish Good (FG)" />
                            <span class="rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                Khusus FG
                            </span>
                        </div>
                        <button
                            type="button"
                            @click="toggleProdukDropdown"
                            class="mt-1 flex w-full items-center justify-between gap-2 rounded-md border border-gray-300 bg-white px-3 py-2 text-left text-sm shadow-sm hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        >
                            <span
                                :class="
                                    selectedProduk
                                        ? 'text-gray-900 dark:text-gray-100'
                                        : 'text-gray-400 dark:text-gray-500'
                                "
                            >
                                {{
                                    selectedProduk
                                        ? `${selectedProduk.nama_produk} (${selectedProduk.kode_produk})`
                                        : 'Pilih Produk Finish Good (FG)'
                                }}
                            </span>
                            <svg
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                :class="produkDropdownOpen ? 'rotate-180' : ''"
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

                        <div v-if="produkDropdownOpen" class="relative mt-1">
                            <div
                                class="fixed inset-0 z-10"
                                @click="produkDropdownOpen = false"
                            ></div>
                            <div
                                class="relative z-20 rounded-md border border-gray-300 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                            >
                                <div class="border-b border-gray-100 p-2 dark:border-gray-700">
                                    <input
                                        v-model="produkSearch"
                                        type="search"
                                        placeholder="Cari nama atau kode produk Finish Good (FG)..."
                                        class="block w-full rounded-md border-gray-300 px-3 py-1.5 text-sm shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:placeholder:text-gray-500"
                                        autofocus
                                    />
                                    <p
                                        class="mt-1 px-1 text-[10px] text-gray-500 dark:text-gray-400"
                                    >
                                        Khusus Finish Good (FG) aktif · {{ filteredProduks.length }} dari
                                        {{ produks.length }} produk
                                    </p>
                                </div>
                                <ul
                                    class="max-h-56 overflow-y-auto py-1"
                                    role="listbox"
                                >
                                    <li
                                        v-for="p in filteredProduks"
                                        :key="p.id"
                                        role="option"
                                        :aria-selected="
                                            Number(createForm.produk_id) === p.id
                                        "
                                        @click="chooseProduk(p)"
                                        class="cursor-pointer px-3 py-2 text-sm hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                                        :class="
                                            Number(createForm.produk_id) === p.id
                                                ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200'
                                                : 'text-gray-700 dark:text-gray-300'
                                        "
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <span>{{ p.nama_produk }}</span>
                                                <span class="ml-1 font-mono text-[11px] text-gray-400">({{ p.kode_produk }})</span>
                                            </div>
                                            <span class="shrink-0 rounded bg-emerald-50 px-1 py-0.2 text-[9px] font-bold text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300">
                                                FG
                                            </span>
                                        </div>
                                    </li>
                                    <li
                                        v-if="filteredProduks.length === 0"
                                        class="px-3 py-4 text-center text-xs text-gray-400"
                                    >
                                        Produk Finish Good tidak ditemukan untuk "{{ produkSearch }}"
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <InputError
                            :message="createForm.errors.produk_id"
                            class="mt-1"
                        />
                    </div>

                    <!-- Nomor Batch -->
                    <div>
                        <div class="flex items-center justify-between">
                            <InputLabel value="Nomor Batch" />
                            <span
                                v-if="currentCreateProductBatches.length > 0"
                                class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ currentCreateProductBatches.length }} Batch Odoo (FG) Tersedia
                            </span>
                        </div>
                        <input
                            v-model="createForm.batch_number"
                            type="text"
                            list="mps-create-batch-list"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 font-mono text-xs text-gray-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="Pilih batch Odoo (FG) di bawah atau ketik nomor batch..."
                        />
                        <datalist id="mps-create-batch-list">
                            <option
                                v-for="b in currentCreateProductBatches"
                                :key="`${b.batch_number}-${b.proses}-${b.id}`"
                                :value="b.batch_number"
                            >
                                {{ b.proses ? `[${b.proses.toUpperCase()}] ` : '' }}{{ b.odoo_mo_id ? `MO #${b.odoo_mo_id} (${b.mo_status ?? 'draft'})` : '' }}
                            </option>
                        </datalist>

                        <!-- Quick Batch Selector Pills if Odoo batches available -->
                        <div
                            v-if="currentCreateProductBatches.length > 0"
                            class="mt-2 space-y-1.5 rounded-xl border border-indigo-100 bg-indigo-50/50 p-2.5 dark:border-indigo-900/50 dark:bg-indigo-950/20"
                        >
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="flex items-center gap-1 font-semibold text-indigo-900 dark:text-indigo-300">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                                    </svg>
                                    Pilih Batch Bawaan Odoo (FG):
                                </span>
                                <span class="text-[10px] text-gray-500">Klik untuk memilih & atur Work Center</span>
                            </div>
                            <div class="flex max-h-32 flex-wrap gap-1.5 overflow-y-auto pr-0.5">
                                <button
                                    v-for="b in currentCreateProductBatches"
                                    :key="`${b.batch_number}-${b.proses}-${b.id}`"
                                    type="button"
                                    class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[11px] font-medium transition shadow-xs"
                                    :class="
                                        createForm.batch_number === b.batch_number
                                            ? 'bg-indigo-600 font-bold text-white shadow-sm ring-1 ring-indigo-500'
                                            : 'border border-gray-200 bg-white text-gray-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/60'
                                    "
                                    @click="selectBatch(b)"
                                >
                                    <span class="font-mono font-bold">{{ b.batch_number }}</span>
                                    <span
                                        v-if="b.proses"
                                        class="rounded px-1.5 py-0.2 text-[9px] font-extrabold uppercase tracking-wide"
                                        :class="
                                            createForm.batch_number === b.batch_number
                                                ? 'bg-white/20 text-white'
                                                : b.proses === 'mixing'
                                                ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200'
                                                : b.proses === 'filling'
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200'
                                                : 'bg-purple-100 text-purple-800 dark:bg-purple-950 dark:text-purple-200'
                                        "
                                    >
                                        {{ b.proses === 'secondary' ? 'packaging' : b.proses }}
                                    </span>
                                </button>
                            </div>
                        </div>
                        <InputError :message="createForm.errors.batch_number" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Work Center" />
                        <select
                            v-model="createForm.work_center_id"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs text-gray-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            required
                        >
                            <option value="" disabled>Pilih Work Center</option>
                            <optgroup
                                v-for="type in ['mixing', 'filling', 'secondary']"
                                :key="type"
                                :label="type === 'mixing' ? 'Mixing' : type === 'filling' ? 'Filling' : 'Secondary'"
                            >
                                <option
                                    v-for="wc in workCenters.filter(w => w.type === type && w.is_active)"
                                    :key="wc.id"
                                    :value="wc.id"
                                >
                                    {{ wc.code }} - {{ wc.name }} ({{ type === 'mixing' ? 'Mixing' : type === 'filling' ? 'Filling' : 'Secondary' }})
                                </option>
                            </optgroup>
                        </select>
                        <InputError :message="createForm.errors.work_center_id" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Bulan & Tahun" />
                        <input
                            v-model="createForm.month_year"
                            type="month"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            required
                        />
                        <InputError :message="createForm.errors.month_year" class="mt-1" />
                    </div>

                    <div>
                        <InputLabel value="Catatan" />
                        <textarea
                            v-model="createForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-gray-300 text-xs text-gray-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                        ></textarea>
                    </div>
                </form>
            </template>
            <template #footer>
                <SecondaryButton @click="closeCreate">Batal</SecondaryButton>
                <PrimaryButton :disabled="createForm.processing" type="submit" form="create-form">
                    {{ createForm.processing ? 'Menyimpan...' : 'Simpan MPS Plan' }}
                </PrimaryButton>
            </template>
        </Modal>

        <!-- Delete Confirmation Modal -->
        <Modal :show="!!deleteId" @close="deleteId = null">
            <template #title>Hapus MPS Plan</template>
            <template #content>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Apakah Anda yakin ingin menghapus MPS Plan ini? Tindakan ini tidak dapat dibatalkan.
                </p>
            </template>
            <template #footer>
                <SecondaryButton @click="deleteId = null">Batal</SecondaryButton>
                <PrimaryButton @click="destroy" :disabled="deleteId === null" class="bg-rose-600 hover:bg-rose-700">
                    Hapus
                </PrimaryButton>
            </template>
        </Modal>
    </AuthenticatedLayout>
</template>