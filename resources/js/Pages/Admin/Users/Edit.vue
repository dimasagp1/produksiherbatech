<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    user: {
        id: number;
        name: string;
        email: string;
        is_active: boolean;
        role: string | null;
    };
    roles: string[];
}>();
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    role: props.user.role ?? '',
    is_active: props.user.is_active as boolean,
});
function submit() {
    form.put(route('admin.users.update', props.user.id));
}
</script>

<template>
    <Head title="Edit User" />
    <AuthenticatedLayout>
        <template #header
            ><div class="flex items-center justify-between">
                <h2
                    class="text-lg font-semibold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                >
                    Edit User
                </h2>
                <Link
                    :href="route('admin.users.index')"
                    class="text-xs text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-100 sm:text-sm"
                    >Kembali</Link
                >
            </div></template
        >
        <div class="mx-auto max-w-2xl px-3 py-4 sm:px-6 sm:py-6">
            <div class="rounded-lg bg-white p-6 shadow dark:bg-gray-800">
                <form @submit.prevent="submit" class="space-y-4">
                    <div>
                        <InputLabel value="Nama" /><TextInput
                            v-model="form.name"
                            class="mt-1 block w-full"
                            required
                        /><InputError
                            :message="form.errors.name"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Email" /><TextInput
                            v-model="form.email"
                            type="email"
                            class="mt-1 block w-full"
                            required
                        /><InputError
                            :message="form.errors.email"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel
                            value="Password (kosongkan jika tidak ganti)"
                        /><TextInput
                            v-model="form.password"
                            type="password"
                            class="mt-1 block w-full"
                        /><InputError
                            :message="form.errors.password"
                            class="mt-1"
                        />
                    </div>
                    <div>
                        <InputLabel value="Konfirmasi Password" /><TextInput
                            v-model="form.password_confirmation"
                            type="password"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel value="Role" />
                        <select
                            v-model="form.role"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                            required
                        >
                            <option v-for="r in roles" :key="r" :value="r">
                                {{ r }}
                            </option>
                        </select>
                        <InputError :message="form.errors.role" class="mt-1" />
                    </div>
                    <label class="flex items-center gap-2"
                        ><input
                            type="checkbox"
                            v-model="form.is_active"
                            class="rounded"
                        />
                        <span class="text-sm">Akun Aktif</span></label
                    >
                    <div class="flex justify-end">
                        <PrimaryButton :disabled="form.processing"
                            >Update</PrimaryButton
                        >
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
