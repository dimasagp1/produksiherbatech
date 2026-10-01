<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import OdooProductSyncModal from '@/Components/OdooProductSyncModal.vue';
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const isManager = computed(
    () => (usePage().props as any).auth?.user?.role === 'manager',
);

interface Produk {
    id: number;
    odoo_id?: number | null;
    kode_produk: string;
    nama_produk: string;
    proses_default: string;
    odoo_uom?: string | null;
    status_aktif: boolean;
    odoo_synced_at?: string | null;
}

defineProps<{
    produks: {
        data: Produk[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
}>();

const confirmDelete = ref<boolean>(false);
const deleteId = ref<number | null>(null);
const search = ref('');
const showSyncModal = ref(false);
const syncSuccessMessage = ref<string | null>(null);

function handleSynced(result: any) {
    syncSuccessMessage.value = result.message;
    router.reload({ only: ['produks'] });
}

function openDelete(id: number) {
    deleteId.value = id;
    confirmDelete.value = true;
}

function destroy() {
    if (deleteId.value) {
        router.delete(route('admin.produk.destroy', deleteId.value), {
            onSuccess: () => {
                confirmDelete.value = false;
                deleteId.value = null;
            },
        });
    }
}

function goToPage(page: number) {
    router.get(
        route('admin.produk.index'),
        { page, search: search.value },
        { preserveState: true },
    );
}

function doSearch() {
    router.get(
        route('admin.produk.index'),
        { search: search.value, page: 1 },
        { preserveState: true },
    );
}

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
};
</script>

<template>
    <Head title="Data Produk" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2
                        class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        Data Produk
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Kelola katalog master produk dan integrasi Odoo
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="!isManager"
                        :href="route('admin.settings.hris.index')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-sky-300 bg-sky-50 px-3 py-2 text-xs font-semibold text-sky-700 shadow-sm transition hover:bg-sky-100 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-300 dark:hover:bg-sky-900/50"
                        title="Buka Pengaturan Integrasi API HRIS"
                    >
                        <svg class="h-3.5 w-3.5 text-sky-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
                        </svg>
                        API HRIS
                    </Link>
                    <Link
                        v-if="!isManager"
                        :href="route('admin.odoo.settings')"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                        title="Buka Pengaturan Koneksi Odoo"
                    >
                        <svg
                            class="h-3.5 w-3.5 text-gray-500"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 0 1 0 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
                            />
                        </svg>
                        Setting Odoo
                    </Link>
                    <button
                        v-if="!isManager"
                        @click="showSyncModal = true"
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg border border-purple-300 bg-purple-50 px-3.5 py-2 text-xs font-semibold text-purple-700 shadow-sm transition hover:bg-purple-100 dark:border-purple-800 dark:bg-purple-950/40 dark:text-purple-300 dark:hover:bg-purple-900/50"
                        title="Tarik & Pilih master produk terbaru dari Odoo ERP"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="2"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"
                            />
                        </svg>
                        ⚡ Tarik dari Odoo
                    </button>
                    <Link
                        v-if="!isManager"
                        :href="route('admin.produk.create')"
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                    >
                        <svg
                            class="h-3.5 w-3.5"
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
                        Tambah Produk
                    </Link>
                    <span
                        v-else
                        class="rounded bg-amber-100 px-3 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
                        >Read-only</span
                    >
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6">
            <div
                v-if="syncSuccessMessage"
                class="mb-4 flex items-center justify-between rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300"
            >
                <span>✓ {{ syncSuccessMessage }}</span>
                <button
                    @click="syncSuccessMessage = null"
                    class="ml-3 font-bold text-emerald-600 hover:text-emerald-800 dark:text-emerald-400"
                >
                    ✕
                </button>
            </div>

            <div
                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Cari kode atau nama produk..."
                    class="w-full max-w-md"
                    @search="doSearch"
                />
                <span class="text-xs text-gray-500 dark:text-gray-400"
                    >Total: <strong>{{ produks.total }}</strong> Produk</span
                >
            </div>

            <!-- DESKTOP TABLE VIEW (md:block) -->
            <div
                class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block"
            >
                <div class="overflow-x-auto">
                    <table
                        class="min-w-full divide-y divide-gray-200 dark:divide-gray-700"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Kode
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Nama Produk
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Sumber / UoM
                                </th>
                                <th
                                    class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Proses Default
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
                            <tr v-if="produks.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data
                                </td>
                            </tr>
                            <tr
                                v-for="item in produks.data"
                                :key="item.id"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700"
                            >
                                <td
                                    class="whitespace-nowrap px-6 py-4 font-mono text-sm text-gray-900 dark:text-gray-100"
                                >
                                    {{ item.kode_produk }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ item.nama_produk }}
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-xs">
                                    <span
                                        v-if="item.odoo_id"
                                        class="inline-flex items-center gap-1 rounded bg-purple-100 px-2 py-0.5 font-medium text-purple-800 dark:bg-purple-900/40 dark:text-purple-300"
                                        :title="'Odoo ID: ' + item.odoo_id"
                                    >
                                        ⚡ Odoo #{{ item.odoo_id }}
                                        {{
                                            item.odoo_uom
                                                ? '· ' + item.odoo_uom
                                                : ''
                                        }}
                                    </span>
                                    <span v-else class="text-gray-400"
                                        >Manual</span
                                    >
                                </td>
                                <td
                                    class="whitespace-nowrap px-6 py-4 text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {{
                                        prosesLabel[item.proses_default] ??
                                        item.proses_default
                                    }}
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
                                                    'admin.produk.edit',
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
                        :current-page="produks.current_page"
                        :last-page="produks.last_page"
                        :total="produks.total"
                        :per-page="produks.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="produks.data.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                >
                    Tidak ada data produk.
                </div>

                <div
                    v-else
                    v-for="item in produks.data"
                    :key="item.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700"
                    >
                        <div>
                            <span
                                class="rounded bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
                            >
                                {{ item.kode_produk }}
                            </span>
                            <h3
                                class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                            >
                                {{ item.nama_produk }}
                            </h3>
                        </div>
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

                    <div class="my-2.5 grid grid-cols-2 gap-2 text-xs">
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Proses Default</span
                            >
                            <span
                                class="font-medium text-gray-800 dark:text-gray-200"
                                >{{
                                    prosesLabel[item.proses_default] ??
                                    item.proses_default
                                }}</span
                            >
                        </div>
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Sumber Master</span
                            >
                            <span
                                v-if="item.odoo_id"
                                class="font-semibold text-purple-700 dark:text-purple-300"
                            >
                                ⚡ Odoo #{{ item.odoo_id }}
                                {{ item.odoo_uom ? `(${item.odoo_uom})` : '' }}
                            </span>
                            <span
                                v-else
                                class="text-gray-500 dark:text-gray-400"
                                >Manual Entry</span
                            >
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-end gap-3 border-t border-gray-100 pt-2.5 text-xs dark:border-gray-700"
                    >
                        <template v-if="!isManager">
                            <Link
                                :href="route('admin.produk.edit', item.id)"
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
                        :current-page="produks.current_page"
                        :last-page="produks.last_page"
                        :total="produks.total"
                        :per-page="produks.per_page"
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
                    Apakah Anda yakin ingin menghapus produk ini?
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="confirmDelete = false"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="destroy">Hapus</DangerButton>
                </div>
            </div>
        </Modal>

        <!-- POPUP MODAL PILIH PRODUK ODOO -->
        <OdooProductSyncModal
            :show="showSyncModal"
            @close="showSyncModal = false"
            @synced="handleSynced"
        />
    </AuthenticatedLayout>
</template>
