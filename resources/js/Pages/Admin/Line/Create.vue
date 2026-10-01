<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    kode_line: '',
    nama_line: '',
});

function submit() {
    form.post(route('admin.line.store'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Tambah Line" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Tambah Line
                </h2>
                <Link
                    :href="route('admin.line.index')"
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
                        <InputLabel for="kode_line" value="Kode Line" />
                        <TextInput
                            id="kode_line"
                            v-model="form.kode_line"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="form.errors.kode_line"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-6">
                        <InputLabel for="nama_line" value="Nama Line" />
                        <TextInput
                            id="nama_line"
                            v-model="form.nama_line"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="form.errors.nama_line"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <PrimaryButton :disabled="form.processing"
                            >Simpan</PrimaryButton
                        >
                        <Link :href="route('admin.line.index')"
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
