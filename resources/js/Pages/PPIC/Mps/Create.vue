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

const props = defineProps<{
    produks: Produk[];
    workCenters: WorkCenter[];
    defaultMonth: string;
}>();

const createForm = useForm({
    produk_id: '',
    work_center_id: '',
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

function toggleProdukDropdown() {
    produkDropdownOpen.value = !produkDropdownOpen.value;
    if (produkDropdownOpen.value) {
        produkSearch.value = '';
    }
}

function chooseProduk(p: Produk) {
    createForm.produk_id = String(p.id);
    produkDropdownOpen.value = false;
    produkSearch.value = '';
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