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
    month_year: string;
    status: string;
    notes: string | null;
    created_by: number;
    produk: Produk;
    workCenter: WorkCenter;
    creator: { id: number; name: string };
    items_count: number;
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
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});

const monthYearInput = ref(props.monthYear);
const statusFilter = ref(props.status);

const showCreate = ref(false);
const deleteId = ref<number | null>(null);

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

function openCreate() {
    showCreate.value = true;
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
                                <th class="px-4 py-3">Work Center</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Items</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-if="props.plans.data.length === 0" class="text-center">
                                <td colspan="6" class="py-12 text-gray-400 dark:text-gray-500">
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
                                    {{ formatMonthYear(plan.month_year) }}
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
                                    <span
                                        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[11px] font-bold"
                                        :class="{
                                            'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300': plan.workCenter?.type === 'mixing',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300': plan.workCenter?.type === 'filling',
                                            'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300': plan.workCenter?.type === 'secondary',
                                        }"
                                    >
                                        {{ plan.workCenter?.code }} - {{ plan.workCenter?.name }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3">
                                    <span :class="['inline-flex w-max rounded-md px-2 py-0.5 text-[10px] font-bold uppercase', getStatusColor(plan.status)]">
                                        {{ getStatusLabel(plan.status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-4 py-3 text-right font-mono text-sm text-gray-900 dark:text-gray-100">
                                    {{ plan.items_count }}
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
        <Modal :show="showCreate" @close="showCreate = false">
            <template #title>Buat MPS Plan Baru</template>
            <template #content>
                <form id="create-form" @submit.prevent="createForm.post(route('ppic.mps.store'))" class="space-y-4">
                    <div>
                        <InputLabel value="Produk" />
                        <select
                            v-model="createForm.produk_id"
                            class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs text-gray-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
                            required
                        >
                            <option value="" disabled>Pilih Produk</option>
                            <option v-for="p in produks" :key="p.id" :value="p.id">
                                {{ p.nama_produk }} ({{ p.kode_produk }})
                            </option>
                        </select>
                        <InputError :message="createForm.errors.produk_id" class="mt-1" />
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
                <SecondaryButton @click="showCreate = false">Batal</SecondaryButton>
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