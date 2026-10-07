<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Props {}

const createForm = useForm({
    code: '',
    name: '',
    type: 'mixing',
    standard_ct_seconds: 0,
    fit_mp: 0,
    shift_hours: 6.5,
    is_active: true,
    notes: '',
});

function submit() {
    createForm.post(route('admin.work-center.store'), {
        onSuccess: () => {
            router.visit(route('admin.work-center.index'));
        },
    });
}
</script>

<template>
    <Head title="Tambah Work Center" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        Tambah Work Center
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Buat Work Center baru untuk perhitungan target berbasis CT
                    </p>
                </div>
                <Link
                    :href="route('admin.work-center.index')"
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
                        <InputLabel value="Code" />
                        <TextInput
                            v-model="createForm.code"
                            class="mt-1.5 block w-full text-xs"
                            maxlength="50"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="createForm.errors.code" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Nama" />
                        <TextInput
                            v-model="createForm.name"
                            class="mt-1.5 block w-full text-xs"
                            maxlength="100"
                            required
                        />
                        <InputError :message="createForm.errors.name" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Tipe" />
                        <select
                            v-model="createForm.type"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="mixing">Mixing</option>
                            <option value="filling">Filling</option>
                            <option value="secondary">Secondary (Packing/Labeling)</option>
                        </select>
                        <InputError :message="createForm.errors.type" class="mt-1" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="CT (detik/pcs)" />
                            <TextInput
                                v-model="createForm.standard_ct_seconds"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="createForm.errors.standard_ct_seconds" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="MP Standar" />
                            <TextInput
                                v-model="createForm.fit_mp"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="createForm.errors.fit_mp" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Jam Shift" />
                            <TextInput
                                v-model="createForm.shift_hours"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                max="24"
                                step="0.5"
                                required
                            />
                            <InputError :message="createForm.errors.shift_hours" class="mt-1" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="createForm.is_active"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Aktif</span>
                            </label>
                        </div>
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
                        :href="route('admin.work-center.index')"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Batal
                    </Link>
                    <PrimaryButton :disabled="createForm.processing" class="rounded-xl px-6 py-2.5 text-xs font-bold">
                        {{ createForm.processing ? 'Menyimpan...' : 'Simpan Work Center' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>