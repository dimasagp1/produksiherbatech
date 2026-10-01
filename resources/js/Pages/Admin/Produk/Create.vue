<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    kode_produk: '',
    nama_produk: '',
    proses_default: 'mixing',
});

const prosesOptions = [
    { value: 'mixing', label: 'Mixing' },
    { value: 'filling', label: 'Filling' },
    { value: 'packing', label: 'Packing' },
];

function submit() {
    form.post(route('admin.produk.store'), {
        onFinish: () => form.reset(),
    });
}
</script>

<template>
    <Head title="Tambah Produk" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Tambah Produk
                </h2>
                <Link
                    :href="route('admin.produk.index')"
                    class="text-sm text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100"
                >
                    Kembali
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-2xl px-3 sm:px-6 lg:px-8">
            <div
                class="overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg"
            >
                <form @submit.prevent="submit" class="p-6">
                    <div class="mb-4">
                        <InputLabel for="kode_produk" value="Kode Produk" />
                        <TextInput
                            id="kode_produk"
                            v-model="form.kode_produk"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="form.errors.kode_produk"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-4">
                        <InputLabel for="nama_produk" value="Nama Produk" />
                        <TextInput
                            id="nama_produk"
                            v-model="form.nama_produk"
                            type="text"
                            class="mt-1 block w-full"
                            required
                        />
                        <InputError
                            :message="form.errors.nama_produk"
                            class="mt-2"
                        />
                    </div>

                    <div class="mb-6">
                        <InputLabel
                            for="proses_default"
                            value="Proses Default"
                        />
                        <select
                            id="proses_default"
                            v-model="form.proses_default"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
                            required
                        >
                            <option
                                v-for="opt in prosesOptions"
                                :key="opt.value"
                                :value="opt.value"
                            >
                                {{ opt.label }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.proses_default"
                            class="mt-2"
                        />
                    </div>

                    <div class="flex items-center gap-2">
                        <PrimaryButton :disabled="form.processing"
                            >Simpan</PrimaryButton
                        >
                        <Link :href="route('admin.produk.index')">
                            <SecondaryButton type="button"
                                >Batal</SecondaryButton
                            >
                        </Link>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
