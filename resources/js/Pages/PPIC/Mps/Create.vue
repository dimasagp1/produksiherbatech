<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';

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
                    <div>
                        <InputLabel value="Produk" />
                        <select
                            v-model="createForm.produk_id"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="" disabled>Pilih Produk</option>
                            <option
                                v-for="p in produks"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nama_produk }} ({{ p.kode_produk }})
                            </option>
                        </select>
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