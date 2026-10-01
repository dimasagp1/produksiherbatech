<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    alasanDowntime: {
        id: number;
        nama_alasan: string;
        tipe_input: string;
        durasi_default_menit: number | null;
        status_aktif: boolean;
    };
}>();

const form = useForm({
    nama_alasan: props.alasanDowntime.nama_alasan,
    tipe_input: props.alasanDowntime.tipe_input,
    durasi_default_menit: String(
        props.alasanDowntime.durasi_default_menit ?? '',
    ),
    status_aktif: props.alasanDowntime.status_aktif,
});

const tipeOptions = [
    { value: 'manual', label: 'Manual' },
    { value: 'default_hardcode', label: 'Default/Hardcode' },
];

function submit() {
    form.put(route('admin.alasan-downtime.update', props.alasanDowntime.id));
}
</script>

<template>
    <Head title="Edit Alasan Downtime" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Edit Alasan Downtime
                </h2>
                <Link
                    :href="route('admin.alasan-downtime.index')"
                    class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                    >Kembali</Link
                >
            </div>
        </template>

        <div class="mx-auto max-w-2xl px-3 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg"
            >
                <form @submit.prevent="submit" class="p-6">
                    <div class="mb-4">
                        <InputLabel for="nama_alasan" value="Nama Alasan" />
                        <TextInput
                            id="nama_alasan"
                            v-model="form.nama_alasan"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="form.errors.nama_alasan"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-4">
                        <InputLabel for="tipe_input" value="Tipe Input" />
                        <select
                            id="tipe_input"
                            v-model="form.tipe_input"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            required
                        >
                            <option
                                v-for="opt in tipeOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.tipe_input"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-4">
                        <InputLabel
                            for="durasi_default_menit"
                            value="Durasi Default (menit) — opsional"
                        />
                        <TextInput
                            id="durasi_default_menit"
                            v-model="form.durasi_default_menit"
                            type="number"
                            min="0"
                            class="mt-1 block w-full"
                        />
                        <InputError
                            :message="form.errors.durasi_default_menit"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-6">
                        <InputLabel for="status_aktif" value="Status" />
                        <label class="mt-1 flex items-center gap-2">
                            <input
                                id="status_aktif"
                                v-model="form.status_aktif"
                                type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900"
                            />
                            <span
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >Aktif</span
                            >
                        </label>
                    </div>

                    <div class="flex items-center gap-2">
                        <PrimaryButton :disabled="form.processing"
                            >Simpan</PrimaryButton
                        >
                        <Link :href="route('admin.alasan-downtime.index')"
                            ><SecondaryButton type="button"
                                >Batal</SecondaryButton
                            ></Link
                        >
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
