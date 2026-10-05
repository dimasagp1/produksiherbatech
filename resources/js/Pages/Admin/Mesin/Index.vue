<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const isManager = computed(
    () => (usePage().props as any).auth?.user?.role === 'manager',
);

interface Mesin {
    id: number;
    nama_mesin: string;
    ct: number;
    status_aktif: boolean;
}

defineProps<{
    mesins: {
        data: Mesin[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const confirmDelete = ref<boolean>(false);
const deleteId = ref<number | null>(null);
const search = ref('');

function openDelete(id: number) {
    deleteId.value = id;
    confirmDelete.value = true;
}

function destroy() {
    if (deleteId.value) {
        router.delete(route('admin.mesin.destroy', deleteId.value), {
            onSuccess: () => {
                confirmDelete.value = false;
                deleteId.value = null;
            },
        });
    }
}

function goToPage(page: number) {
    router.get(
        route('admin.mesin.index'),
        { page, search: search.value },
        { preserveState: true },
    );
}

function doSearch() {
    router.get(
        route('admin.mesin.index'),
        { search: search.value, page: 1 },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Data Mesin" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2
                    class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                >
                    Data Mesin
                </h2>
                <Link
                    v-if="!isManager"
                    :href="route('admin.mesin.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    Tambah Mesin
                </Link>
                <span
                    v-else
                    class="rounded bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                    >Read-only</span
                >
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6">
            <div
                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Cari nama mesin..."
                    class="w-full max-w-md"
                    @search="doSearch"
                />
                <span class="text-xs text-gray-500 dark:text-gray-400"
                    >Total: <strong>{{ mesins.total }}</strong> Mesin</span
                >
            </div>

            <!-- DESKTOP TABLE VIEW (md:block) -->
            <div
                class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-[600px] w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Nama Mesin
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    CT (menit)
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Status
                                </th>
                                <th
                                    class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                        >
                            <tr v-if="mesins.data.length === 0">
                                <td
                                    colspan="4"
                                    class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data
                                </td>
                            </tr>
                            <tr
                                v-for="item in mesins.data"
                                :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ item.nama_mesin }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ item.ct }} mnt
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-sm">
                                    <span
                                        :class="
                                            item.status_aktif
                                                ? 'font-medium text-green-600 dark:text-green-400'
                                                : 'font-medium text-red-600 dark:text-red-400'
                                        "
                                    >
                                        {{
                                            item.status_aktif
                                                ? 'Aktif'
                                                : 'Nonaktif'
                                        }}
                                    </span>
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-right text-sm"
                                >
                                    <template v-if="!isManager">
                                        <Link
                                            :href="
                                                route(
                                                    'admin.mesin.edit',
                                                    item.id,
                                                )
                                            "
                                            class="mr-3 text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300"
                                            >Edit</Link
                                        >
                                        <button
                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                            @click="openDelete(item.id)"
                                        >
                                            Hapus
                                        </button>
                                    </template>
                                    <span v-else class="text-xs text-gray-400"
                                        >Read-only</span
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="border-t border-gray-200 bg-white px-6 py-3 dark:border-gray-700 dark:bg-gray-800"
                >
                    <Pagination
                        :current-page="mesins.current_page"
                        :last-page="mesins.last_page"
                        :total="mesins.total"
                        :per-page="mesins.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="mesins.data.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                >
                    Tidak ada data mesin.
                </div>

                <div
                    v-else
                    v-for="item in mesins.data"
                    :key="item.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700"
                    >
                        <h3
                            class="text-sm font-bold text-gray-900 dark:text-gray-100"
                        >
                            {{ item.nama_mesin }}
                        </h3>
                        <span
                            class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                            :class="
                                item.status_aktif
                                    ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300'
                                    : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'
                            "
                        >
                            {{ item.status_aktif ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <div
                        class="my-2.5 flex items-center justify-between rounded-lg bg-gray-50 p-2 text-xs dark:bg-gray-900/40"
                    >
                        <span class="text-gray-500 dark:text-gray-400"
                            >Cycle Time (CT):</span
                        >
                        <span
                            class="font-mono font-bold text-gray-900 dark:text-gray-100"
                            >{{ item.ct }} menit</span
                        >
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-gray-100 pt-2.5 text-xs dark:border-gray-700"
                    >
                        <template v-if="!isManager">
                            <Link
                                :href="route('admin.mesin.edit', item.id)"
                                class="rounded-md bg-indigo-50 px-3 py-1 font-semibold text-indigo-700 transition hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300"
                            >
                                Edit
                            </Link>
                            <button
                                type="button"
                                @click="openDelete(item.id)"
                                class="rounded-md bg-red-50 px-3 py-1 font-semibold text-red-700 transition hover:bg-red-100 dark:bg-red-950/40 dark:text-red-300"
                            >
                                Hapus
                            </button>
                        </template>
                        <span v-else class="text-xs text-gray-400"
                            >Read-only</span
                        >
                    </div>
                </div>

                <div
                    class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg"
                >
                    <Pagination
                        :current-page="mesins.current_page"
                        :last-page="mesins.last_page"
                        :total="mesins.total"
                        :per-page="mesins.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>

        <Modal :show="confirmDelete" @close="confirmDelete = false">
            <div class="p-6">
                <h2
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Konfirmasi Hapus
                </h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Apakah Anda yakin ingin menghapus mesin ini?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="confirmDelete = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="destroy">Hapus</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
