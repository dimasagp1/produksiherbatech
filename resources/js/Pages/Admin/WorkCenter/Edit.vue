<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: 'mixing' | 'filling' | 'secondary';
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    is_active: boolean;
    notes: string | null;
}

const props = defineProps<{
    workCenter: WorkCenter;
}>();

const editForm = useForm({
    code: props.workCenter.code,
    name: props.workCenter.name,
    type: props.workCenter.type,
    standard_ct_seconds: props.workCenter.standard_ct_seconds,
    fit_mp: props.workCenter.fit_mp,
    shift_hours: props.workCenter.shift_hours,
    is_active: props.workCenter.is_active,
    notes: props.workCenter.notes ?? '',
});

function submit() {
    editForm.put(route('admin.work-center.update', props.workCenter.id), {
        onSuccess: () => {
            router.visit(route('admin.work-center.index'));
        },
    });
}
</script>

<template>
    <Head title="Edit Work Center" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        Edit Work Center
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Perbarui data Work Center
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
                            v-model="editForm.code"
                            class="mt-1.5 block w-full text-xs"
                            maxlength="50"
                            required
                            autocomplete="off"
                        />
                        <InputError :message="editForm.errors.code" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Nama" />
                        <TextInput
                            v-model="editForm.name"
                            class="mt-1.5 block w-full text-xs"
                            maxlength="100"
                            required
                        />
                        <InputError :message="editForm.errors.name" class="mt-1" />
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Tipe" />
                        <select
                            v-model="editForm.type"
                            class="mt-1.5 block w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            required
                        >
                            <option value="mixing">Mixing</option>
                            <option value="filling">Filling</option>
                            <option value="secondary">Secondary (Packing/Labeling)</option>
                        </select>
                        <InputError :message="editForm.errors.type" class="mt-1" />
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="CT (detik/pcs)" />
                            <TextInput
                                v-model="editForm.standard_ct_seconds"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="editForm.errors.standard_ct_seconds" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="MP Standar" />
                            <TextInput
                                v-model="editForm.fit_mp"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                step="1"
                                required
                            />
                            <InputError :message="editForm.errors.fit_mp" class="mt-1" />
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-4">
                        <div>
                            <InputLabel value="Jam Shift" />
                            <TextInput
                                v-model="editForm.shift_hours"
                                type="number"
                                class="mt-1.5 block w-full text-xs"
                                min="0"
                                max="24"
                                step="0.5"
                                required
                            />
                            <InputError :message="editForm.errors.shift_hours" class="mt-1" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="editForm.is_active"
                                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-300">Aktif</span>
                            </label>
                        </div>
                    </div>

                    <div class="mt-4">
                        <InputLabel value="Catatan" />
                        <textarea
                            v-model="editForm.notes"
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
                    <PrimaryButton :disabled="editForm.processing" class="rounded-xl px-6 py-2.5 text-xs font-bold">
                        {{ editForm.processing ? 'Menyimpan...' : 'Simpan Perubahan' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>