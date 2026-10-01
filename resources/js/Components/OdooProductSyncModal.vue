<script setup lang="ts">
import axios from 'axios';
import { computed, ref, watch } from 'vue';

interface OdooProductItem {
    odoo_id: number;
    name: string;
    default_code: string;
    uom: string | null;
    active: boolean;
    exists_in_local: boolean;
    local_id: number | null;
    local_name: string | null;
}

const props = defineProps<{
    show: boolean;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (
        e: 'synced',
        result: {
            stats: {
                total_from_odoo: number;
                created: number;
                updated: number;
            };
            message: string;
        },
    ): void;
}>();

const isLoading = ref(false);
const isSyncing = ref(false);
const errorMessage = ref<string | null>(null);
const products = ref<OdooProductItem[]>([]);
const selectedIds = ref<number[]>([]);
const searchQuery = ref('');
const activeFilter = ref<'all' | 'new' | 'existing' | 'selected'>('all');

// Fetch preview data when modal is opened
watch(
    () => props.show,
    (val) => {
        if (val) {
            fetchPreview();
        } else {
            errorMessage.value = null;
            searchQuery.value = '';
            activeFilter.value = 'all';
        }
    },
);

async function fetchPreview() {
    isLoading.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.get(route('admin.odoo.preview-products'));
        if (res.data.success) {
            products.value = res.data.products || [];
            // By default, select all products
            selectedIds.value = products.value.map((p) => p.odoo_id);
        } else {
            errorMessage.value =
                res.data.message || 'Gagal memuat daftar produk dari Odoo.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Gagal menghubungi server Odoo.';
    } finally {
        isLoading.value = false;
    }
}

// Counts
const totalCount = computed(() => products.value.length);
const newCount = computed(
    () => products.value.filter((p) => !p.exists_in_local).length,
);
const existingCount = computed(
    () => products.value.filter((p) => p.exists_in_local).length,
);

// Filtered list based on search and active filter tab
const filteredProducts = computed(() => {
    let list = products.value;

    if (activeFilter.value === 'new') {
        list = list.filter((p) => !p.exists_in_local);
    } else if (activeFilter.value === 'existing') {
        list = list.filter((p) => p.exists_in_local);
    } else if (activeFilter.value === 'selected') {
        const set = new Set(selectedIds.value);
        list = list.filter((p) => set.has(p.odoo_id));
    }

    if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter(
            (p) =>
                p.name.toLowerCase().includes(q) ||
                p.default_code.toLowerCase().includes(q) ||
                (p.uom && p.uom.toLowerCase().includes(q)),
        );
    }

    return list;
});

// Selection calculations
const selectedNewCount = computed(() => {
    const set = new Set(selectedIds.value);
    return products.value.filter(
        (p) => set.has(p.odoo_id) && !p.exists_in_local,
    ).length;
});

const selectedExistingCount = computed(() => {
    const set = new Set(selectedIds.value);
    return products.value.filter((p) => set.has(p.odoo_id) && p.exists_in_local)
        .length;
});

const isAllFilteredSelected = computed(() => {
    if (filteredProducts.value.length === 0) return false;
    const set = new Set(selectedIds.value);
    return filteredProducts.value.every((p) => set.has(p.odoo_id));
});

function toggleSelectAllFiltered() {
    const set = new Set(selectedIds.value);
    if (isAllFilteredSelected.value) {
        // Deselect filtered
        filteredProducts.value.forEach((p) => set.delete(p.odoo_id));
    } else {
        // Select filtered
        filteredProducts.value.forEach((p) => set.add(p.odoo_id));
    }
    selectedIds.value = Array.from(set);
}

function selectOnlyNew() {
    selectedIds.value = products.value
        .filter((p) => !p.exists_in_local)
        .map((p) => p.odoo_id);
}

function clearSelection() {
    selectedIds.value = [];
}

function selectAll() {
    selectedIds.value = products.value.map((p) => p.odoo_id);
}

function toggleProduct(id: number) {
    const idx = selectedIds.value.indexOf(id);
    if (idx > -1) {
        selectedIds.value.splice(idx, 1);
    } else {
        selectedIds.value.push(id);
    }
}

async function handleSyncSubmit() {
    if (isSyncing.value) return;
    if (selectedIds.value.length === 0) {
        alert('Pilih setidaknya satu produk untuk diimpor.');
        return;
    }

    isSyncing.value = true;
    errorMessage.value = null;
    try {
        const res = await axios.post(route('admin.odoo.sync-products'), {
            selected_ids: Array.from(new Set(selectedIds.value)),
        });

        if (res.data.success) {
            emit('synced', res.data);
            emit('close');
        } else {
            errorMessage.value =
                res.data.message || 'Gagal sinkronisasi produk.';
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.message ||
            'Terjadi kesalahan saat sinkronisasi.';
    } finally {
        isSyncing.value = false;
    }
}
</script>

<template>
    <div
        v-if="show"
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title"
        role="dialog"
        aria-modal="true"
    >
        <!-- Backdrop -->
        <div
            class="fixed inset-0 bg-slate-900/75 backdrop-blur-sm transition-opacity"
            @click="!isSyncing && emit('close')"
        ></div>

        <div
            class="flex min-h-screen items-center justify-center p-3 text-center sm:p-4"
        >
            <!-- Modal Dialog -->
            <div
                class="relative flex max-h-[90vh] w-full max-w-4xl transform flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white text-left shadow-2xl transition-all dark:border-slate-800 dark:bg-slate-900"
                @click.stop
            >
                <!-- HEADER -->
                <div
                    class="flex items-center justify-between border-b border-slate-100 bg-slate-50/50 px-6 py-4 dark:border-slate-800/80 dark:bg-slate-900/50"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-100 text-purple-600 dark:bg-purple-950/80 dark:text-purple-400"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke-width="2"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                                />
                            </svg>
                        </div>
                        <div>
                            <h3
                                class="text-base font-bold text-slate-900 dark:text-slate-100"
                                id="modal-title"
                            >
                                Tarik & Pilih Master Produk Odoo
                            </h3>
                            <p
                                class="text-xs text-slate-500 dark:text-slate-400"
                            >
                                Pilih daftar produk dari Odoo ERP yang ingin
                                dimasukkan ke database sistem produksi
                            </p>
                        </div>
                    </div>

                    <button
                        type="button"
                        :disabled="isSyncing"
                        @click="emit('close')"
                        class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-500 disabled:opacity-40 dark:hover:bg-slate-800 dark:hover:text-slate-300"
                    >
                        <svg
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M6 18L18 6M6 6l12 12"
                            />
                        </svg>
                    </button>
                </div>

                <!-- BODY -->
                <div class="flex flex-1 flex-col space-y-4 overflow-hidden p-6">
                    <!-- Error Alert -->
                    <div
                        v-if="errorMessage"
                        class="flex items-start justify-between gap-3 rounded-xl border border-red-200 bg-red-50 p-3.5 text-xs text-red-800 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
                    >
                        <div class="flex items-center gap-2">
                            <span>⚠️</span>
                            <span>{{ errorMessage }}</span>
                        </div>
                        <button
                            v-if="!isLoading"
                            type="button"
                            @click="fetchPreview"
                            class="shrink-0 font-semibold text-red-700 underline hover:no-underline dark:text-red-400"
                        >
                            Coba Lagi
                        </button>
                    </div>

                    <!-- SEARCH & FILTER TOOLBAR -->
                    <div
                        class="flex flex-col items-stretch justify-between gap-3 sm:flex-row sm:items-center"
                    >
                        <!-- Search Box -->
                        <div class="relative flex-1">
                            <div
                                class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"
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
                                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                    />
                                </svg>
                            </div>
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Cari nama produk, kode SKU, atau satuan..."
                                class="w-full rounded-xl border border-slate-200 bg-slate-50/50 py-2 pl-9 pr-8 text-xs text-slate-900 placeholder-slate-400 focus:border-purple-500 focus:bg-white focus:outline-none focus:ring-1 focus:ring-purple-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-100 dark:placeholder-slate-500"
                            />
                            <button
                                v-if="searchQuery"
                                @click="searchQuery = ''"
                                class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-slate-400 hover:text-slate-600"
                            >
                                <svg
                                    class="h-3.5 w-3.5"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fill-rule="evenodd"
                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </button>
                        </div>

                        <!-- Filter Tabs -->
                        <div
                            class="flex items-center self-start rounded-xl bg-slate-100 p-1 text-xs dark:bg-slate-800 sm:self-auto"
                        >
                            <button
                                type="button"
                                @click="activeFilter = 'all'"
                                :class="[
                                    'rounded-lg px-2.5 py-1 font-medium transition',
                                    activeFilter === 'all'
                                        ? 'bg-white text-slate-900 shadow-sm dark:bg-slate-700 dark:text-white'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                                ]"
                            >
                                Semua ({{ totalCount }})
                            </button>
                            <button
                                type="button"
                                @click="activeFilter = 'new'"
                                :class="[
                                    'rounded-lg px-2.5 py-1 font-medium transition',
                                    activeFilter === 'new'
                                        ? 'bg-emerald-500 text-white shadow-sm'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                                ]"
                            >
                                Baru ({{ newCount }})
                            </button>
                            <button
                                type="button"
                                @click="activeFilter = 'existing'"
                                :class="[
                                    'rounded-lg px-2.5 py-1 font-medium transition',
                                    activeFilter === 'existing'
                                        ? 'bg-blue-500 text-white shadow-sm'
                                        : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white',
                                ]"
                            >
                                Sudah Ada ({{ existingCount }})
                            </button>
                        </div>
                    </div>

                    <!-- QUICK SELECT ACTIONS BAR -->
                    <div
                        class="flex flex-wrap items-center justify-between gap-2 border-y border-slate-100 py-2 text-xs dark:border-slate-800"
                    >
                        <div class="flex items-center gap-2">
                            <label
                                class="inline-flex cursor-pointer select-none items-center gap-2 font-medium text-slate-700 dark:text-slate-300"
                            >
                                <input
                                    type="checkbox"
                                    :checked="isAllFilteredSelected"
                                    @change="toggleSelectAllFiltered"
                                    class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                                <span
                                    >Pilih Semua yang Ditampilkan ({{
                                        filteredProducts.length
                                    }})</span
                                >
                            </label>
                        </div>

                        <div class="flex items-center gap-2 text-[11px]">
                            <button
                                type="button"
                                @click="selectAll"
                                class="text-purple-600 hover:underline dark:text-purple-400"
                            >
                                Pilih Semua ({{ totalCount }})
                            </button>
                            <span class="text-slate-300 dark:text-slate-700"
                                >•</span
                            >
                            <button
                                type="button"
                                @click="selectOnlyNew"
                                class="text-emerald-600 hover:underline dark:text-emerald-400"
                            >
                                Hanya Produk Baru ({{ newCount }})
                            </button>
                            <span class="text-slate-300 dark:text-slate-700"
                                >•</span
                            >
                            <button
                                type="button"
                                @click="clearSelection"
                                class="text-slate-500 hover:underline dark:text-slate-400"
                            >
                                Hapus Pilihan
                            </button>
                        </div>
                    </div>

                    <!-- PRODUCTS LIST CONTAINER -->
                    <div
                        class="relative max-h-[46vh] min-h-[260px] flex-1 divide-y divide-slate-100 overflow-y-auto rounded-xl border border-slate-200 dark:divide-slate-800 dark:border-slate-800"
                    >
                        <!-- LOADING SKELETON -->
                        <div
                            v-if="isLoading"
                            class="flex flex-col items-center justify-center space-y-3 p-8"
                        >
                            <div
                                class="h-8 w-8 animate-spin rounded-full border-2 border-purple-600 border-t-transparent"
                            ></div>
                            <p
                                class="text-xs font-medium text-slate-500 dark:text-slate-400"
                            >
                                Mengambil daftar master produk dari Odoo ERP...
                            </p>
                        </div>

                        <!-- EMPTY STATE -->
                        <div
                            v-else-if="filteredProducts.length === 0"
                            class="p-12 text-center"
                        >
                            <div
                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400 dark:bg-slate-800"
                            >
                                📦
                            </div>
                            <h4
                                class="mt-2 text-xs font-semibold text-slate-800 dark:text-slate-200"
                            >
                                Tidak ada produk yang cocok
                            </h4>
                            <p
                                class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400"
                            >
                                Coba ubah kata kunci pencarian atau tab filter.
                            </p>
                        </div>

                        <!-- PRODUCT ROWS -->
                        <div
                            v-else
                            v-for="item in filteredProducts"
                            :key="item.odoo_id"
                            @click="toggleProduct(item.odoo_id)"
                            :class="[
                                'flex cursor-pointer select-none items-center justify-between gap-3 px-4 py-2.5 transition',
                                selectedIds.includes(item.odoo_id)
                                    ? 'bg-purple-50/50 hover:bg-purple-50 dark:bg-purple-950/20 dark:hover:bg-purple-950/30'
                                    : 'hover:bg-slate-50 dark:hover:bg-slate-800/50',
                            ]"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <input
                                    type="checkbox"
                                    :checked="
                                        selectedIds.includes(item.odoo_id)
                                    "
                                    @click.stop="toggleProduct(item.odoo_id)"
                                    class="h-4 w-4 rounded border-slate-300 text-purple-600 focus:ring-purple-500 dark:border-slate-600 dark:bg-slate-700"
                                />

                                <div class="min-w-0">
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <span
                                            class="font-mono text-xs font-semibold text-slate-900 dark:text-slate-100"
                                        >
                                            {{ item.default_code }}
                                        </span>
                                        <span
                                            class="truncate text-xs font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            {{ item.name }}
                                        </span>
                                    </div>
                                    <div
                                        class="mt-0.5 flex items-center gap-2 text-[11px] text-slate-400"
                                    >
                                        <span
                                            >Satuan:
                                            <b
                                                class="text-slate-600 dark:text-slate-300"
                                                >{{ item.uom || '-' }}</b
                                            ></span
                                        >
                                        <span
                                            v-if="item.exists_in_local"
                                            class="text-blue-500 dark:text-blue-400"
                                        >
                                            • Tersambung ke lokal:
                                            {{ item.local_name }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- BADGE STATUS -->
                            <div class="flex shrink-0 items-center gap-2">
                                <span
                                    v-if="!item.exists_in_local"
                                    class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10.5px] font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300"
                                >
                                    + Baru
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10.5px] font-medium text-blue-700 ring-1 ring-inset ring-blue-600/20 dark:bg-blue-950/50 dark:text-blue-300"
                                >
                                    Sudah Ada
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FOOTER -->
                <div
                    class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 bg-slate-50/80 px-6 py-4 dark:border-slate-800/80 dark:bg-slate-900/80 sm:flex-row"
                >
                    <div class="text-xs text-slate-600 dark:text-slate-400">
                        <span
                            class="font-bold text-slate-900 dark:text-slate-100"
                            >{{ selectedIds.length }}</span
                        >
                        produk terpilih:
                        <span
                            class="font-medium text-emerald-600 dark:text-emerald-400"
                            >({{ selectedNewCount }} Baru</span
                        >,
                        <span
                            class="font-medium text-blue-600 dark:text-blue-400"
                            >{{ selectedExistingCount }} Update)</span
                        >
                    </div>

                    <div
                        class="flex w-full items-center justify-end gap-2 sm:w-auto"
                    >
                        <button
                            type="button"
                            :disabled="isSyncing"
                            @click="emit('close')"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 shadow-sm transition hover:bg-slate-50 disabled:opacity-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700"
                        >
                            Batal
                        </button>

                        <button
                            type="button"
                            :disabled="
                                isSyncing ||
                                selectedIds.length === 0 ||
                                isLoading
                            "
                            @click="handleSyncSubmit"
                            class="inline-flex items-center gap-2 rounded-xl bg-purple-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 disabled:opacity-50 dark:bg-purple-500 dark:hover:bg-purple-600"
                        >
                            <svg
                                v-if="isSyncing"
                                class="h-3.5 w-3.5 animate-spin"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <circle
                                    class="opacity-25"
                                    cx="12"
                                    cy="12"
                                    r="10"
                                    stroke="currentColor"
                                    stroke-width="4"
                                ></circle>
                                <path
                                    class="opacity-75"
                                    fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                ></path>
                            </svg>
                            <span>{{
                                isSyncing
                                    ? 'Sedang Menyinkronkan...'
                                    : `Impor & Sinkronkan (${selectedIds.length}) Produk`
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
