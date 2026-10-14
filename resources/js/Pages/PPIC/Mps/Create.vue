<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

interface Produk {
    id: number;
    kode_produk?: string;
    nama_produk: string;
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
    produks: Produk[];
    workCenters: WorkCenter[];
    odooBatches?: OdooBatch[];
    defaultMonth: string;
}>();

const createForm = useForm({
    produk_id: '',
    work_center_id: '',
    batch_number: '',
    month_year: props.defaultMonth,
    notes: '',
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
        const wc = props.workCenters.find(w => w.type === b.proses && w.is_active);
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
    createForm.batch_number = '';
    produkDropdownOpen.value = false;
    produkSearch.value = '';

    const batches = currentCreateProductBatches.value;
    if (batches.length === 1) {
        selectBatch(batches[0]);
    }
}

function submit() {
    createForm.post(route('ppic.mps.store'), {
        onSuccess: () => {
            router.visit(route('ppic.mps.index'));
        },
    });
}
</script>

<template>
    <Head title="Buat MPS Plan" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        Buat MPS Plan Baru
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Perencanaan produksi bulanan per Produk & Work Center
                    </p>
                </div>
                <Link
                    :href="route('ppic.mps.index')"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                    Kembali
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="submit">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="mb-4">
                        <InputLabel value="Produk" />
                        <button
                            type="button"
                            @click="toggleProdukDropdown"
                            class="mt-1.5 flex w-full items-center justify-between gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-left text-xs shadow-xs hover:border-indigo-400 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300"
                        >
                            <span
                                :class="
                                    selectedProduk
                                        ? 'text-slate-900 dark:text-slate-100 font-medium'
                                        : 'text-slate-400 dark:text-slate-500'
                                "
                            >
                                {{
                                    selectedProduk
                                        ? `${selectedProduk.nama_produk} (${selectedProduk.kode_produk})`
                                        : 'Pilih Produk'
                                }}
                            </span>
                            <svg
                                class="h-4 w-4 shrink-0 text-slate-400 transition-transform"
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
                                class="relative z-20 rounded-xl border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-800"
                            >
                                <div class="border-b border-slate-100 p-2 dark:border-slate-700">
                                    <input
                                        v-model="produkSearch"
                                        type="search"
                                        placeholder="Cari nama atau kode produk..."
                                        class="block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-xs shadow-xs placeholder:text-slate-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-300 dark:placeholder:text-slate-500"
                                        autofocus
                                    />
                                    <p
                                        class="mt-1 px-1 text-[10px] text-slate-500 dark:text-slate-400"
                                    >
                                        Hanya produk aktif · {{ filteredProduks.length }} dari
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
                                        class="cursor-pointer px-3 py-2 text-xs hover:bg-indigo-50 dark:hover:bg-indigo-950/40"
                                        :class="
                                            Number(createForm.produk_id) === p.id
                                                ? 'bg-indigo-50 font-semibold text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-200'
                                                : 'text-slate-700 dark:text-slate-300'
                                        "
                                    >
                                        <div class="font-medium">
                                            {{ p.nama_produk }}
                                        </div>
                                        <div
                                            class="text-[10px] text-slate-400 dark:text-slate-500 font-mono"
                                        >
                                            {{ p.kode_produk ?? '—' }}
                                        </div>
                                    </li>
                                    <li
                                        v-if="filteredProduks.length === 0"
                                        class="px-3 py-4 text-center text-xs text-slate-400"
                                    >
                                        Tidak ada produk yang cocok dengan "{{
                                            produkSearch
                                        }}"
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <InputError :message="createForm.errors.produk_id" class="mt-1" />
                    </div>

                    <!-- Nomor Batch -->
                    <div class="mt-4">
                        <div class="flex items-center justify-between">
                            <InputLabel value="Nomor Batch" />
                            <span
                                v-if="currentCreateProductBatches.length > 0"
                                class="text-[11px] font-semibold text-indigo-600 dark:text-indigo-400"
                            >
                                {{ currentCreateProductBatches.length }} Batch Odoo Tersedia
                            </span>
                        </div>
                        <input
                            v-model="createForm.batch_number"
                            type="text"
                            list="mps-create-page-batch-list"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 font-mono text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            placeholder="Pilih batch Odoo di bawah atau ketik nomor batch..."
                        />
                        <datalist id="mps-create-page-batch-list">
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
                                    Pilih Batch Bawaan Odoo:
                                </span>
                                <span class="text-[10px] text-slate-500 dark:text-slate-400">Klik untuk memilih & atur Work Center</span>
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
                                            : 'border border-slate-200 bg-white text-slate-700 hover:border-indigo-300 hover:bg-indigo-50/50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-indigo-700 dark:hover:bg-indigo-950/60'
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

                    <div class="mt-4">
                        <InputLabel value="Work Center" />
                        <select
                            v-model="createForm.work_center_id"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
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

                    <div class="mt-4">
                        <InputLabel value="Bulan & Tahun" />
                        <input
                            v-model="createForm.month_year"
                            type="month"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        />
                        <InputError :message="createForm.errors.month_year" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Catatan" />
                        <textarea
                            v-model="createForm.notes"
                            rows="3"
                            class="mt-1 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                        ></textarea>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="route('ppic.mps.index')"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Batal
                    </Link>
                    <PrimaryButton :disabled="createForm.processing" class="rounded-xl px-6 py-2.5 text-xs font-bold">
                        {{ createForm.processing ? 'Menyimpan...' : 'Simpan MPS Plan' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>