<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Produk {
    id: number;
    kode_produk: string;
    nama_produk: string;
    item_type?: string;
}
interface Uom {
    id: number;
    code: string;
    name: string;
}
interface BomItem {
    id?: number;
    material_produk_id?: number | null;
    material_name: string;
    quantity: number;
    uom_id?: number | null;
    uom?: Uom | null;
}
interface Bom {
    id: number;
    produk_id?: number;
    version: string;
    is_active: boolean;
    notes?: string | null;
    produk?: Produk;
    items: BomItem[];
    created_at?: string;
}

const props = defineProps<{
    boms: {
        data: Bom[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    produks: Produk[];
    uoms: Uom[];
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.search);
const showForm = ref(false);
const editItem = ref<Bom | null>(null);
const deleteId = ref<number | null>(null);

const form = useForm({
    produk_id: '',
    version: '1',
    notes: '',
    items: [
        { material_produk_id: '', material_name: '', quantity: '', uom_id: '' },
    ] as Array<{
        material_produk_id: string;
        material_name: string;
        quantity: string;
        uom_id: string;
    }>,
});

function openCreate() {
    form.reset();
    form.items = [
        { material_produk_id: '', material_name: '', quantity: '', uom_id: '' },
    ];
    editItem.value = null;
    showForm.value = true;
}

function openEdit(bom: Bom) {
    editItem.value = bom;
    form.produk_id = String(bom.produk_id ?? bom.produk?.id ?? '');
    form.version = bom.version;
    form.notes = bom.notes ?? '';
    form.items = bom.items.map((i) => ({
        material_produk_id: i.material_produk_id
            ? String(i.material_produk_id)
            : '',
        material_name: i.material_name,
        quantity: String(i.quantity),
        uom_id: i.uom_id ? String(i.uom_id) : '',
    }));
    showForm.value = true;
}

function addItem() {
    form.items.push({
        material_produk_id: '',
        material_name: '',
        quantity: '',
        uom_id: '',
    });
}

function removeItem(idx: number) {
    if (form.items.length > 1) form.items.splice(idx, 1);
}

function submit() {
    form.items = form.items.map((i) => ({
        material_produk_id: i.material_produk_id
            ? String(i.material_produk_id)
            : '',
        material_name: i.material_name,
        quantity: String(Number(i.quantity) || 0),
        uom_id: i.uom_id ? String(i.uom_id) : '',
    })) as typeof form.items;

    if (editItem.value) {
        form.put(route('scm.bom.update', editItem.value.id), {
            onSuccess: () => {
                showForm.value = false;
                editItem.value = null;
            },
        });
    } else {
        form.post(route('scm.bom.store'), {
            onSuccess: () => {
                showForm.value = false;
                form.reset();
            },
        });
    }
}

function destroy(id: number) {
    router.delete(route('scm.bom.destroy', id), {
        onSuccess: () => {
            deleteId.value = null;
        },
    });
}

const syncing = ref(false);

function syncBoms() {
    syncing.value = true;
    router.post(
        route('scm.bom.sync-odoo'),
        {},
        {
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function goToPage(page: number) {
    router.get(
        route('scm.bom.index'),
        { page, search: search.value },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="BOM" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Bill of Materials (BOM)
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Standar material per produk — dipakai Material Usage
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        :disabled="syncing"
                        @click="syncBoms"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-2 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <svg
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                            :class="{ 'animate-spin': syncing }"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                            />
                        </svg>
                        <span>{{ syncing ? 'Menyinkronkan...' : 'Sync BOM Odoo' }}</span>
                    </button>
                    <PrimaryButton @click="openCreate">+ BOM Baru</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div
                v-if="flash.success"
                class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
            >
                {{ flash.success }}
            </div>
            <div class="flex items-center justify-between gap-3">
                <SearchInput
                    v-model="search"
                    placeholder="Cari produk / versi..."
                    class="max-w-md"
                    @search="goToPage(1)"
                />
                <span class="text-xs text-gray-500"
                    >Total: {{ boms.total }}</span
                >
            </div>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Produk
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Versi
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Items
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Status
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="bom in boms.data" :key="bom.id">
                            <td
                                class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ bom.produk?.nama_produk ?? '-' }}
                            </td>
                            <td class="px-3 py-2 font-mono">
                                v{{ bom.version }}
                            </td>
                            <td class="px-3 py-2">
                                <div class="space-y-0.5">
                                    <div
                                        v-for="it in bom.items"
                                        :key="it.id"
                                        class="text-gray-600 dark:text-gray-300"
                                    >
                                        {{ it.material_name }} —
                                        <span class="font-mono">{{
                                            it.quantity
                                        }}</span>
                                        {{ it.uom?.code ?? '' }}
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span
                                    :class="
                                        bom.is_active
                                            ? 'bg-emerald-100 text-emerald-800'
                                            : 'bg-gray-100 text-gray-500'
                                    "
                                    class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                    >{{
                                        bom.is_active ? 'Aktif' : 'Nonaktif'
                                    }}</span
                                >
                            </td>
                            <td class="space-x-2 px-3 py-2 text-right">
                                <button
                                    class="font-semibold text-indigo-600 hover:underline"
                                    @click="openEdit(bom)"
                                >
                                    Edit
                                </button>
                                <button
                                    class="font-semibold text-red-600 hover:underline"
                                    @click="deleteId = bom.id"
                                >
                                    Hapus
                                </button>
                            </td>
                        </tr>
                        <tr v-if="boms.data.length === 0">
                            <td
                                colspan="5"
                                class="px-3 py-8 text-center text-gray-400"
                            >
                                Belum ada BOM
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="boms.current_page"
                        :last-page="boms.last_page"
                        :total="boms.total"
                        :per-page="boms.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="2xl">
            <form class="space-y-4 p-6" @submit.prevent="submit">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    {{ editItem ? 'Edit BOM' : 'BOM Baru' }}
                </h3>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Produk" />
                        <select
                            v-model="form.produk_id"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            :disabled="!!editItem"
                            required
                        >
                            <option value="" disabled>Pilih produk</option>
                            <option
                                v-for="p in produks"
                                :key="p.id"
                                :value="p.id"
                            >
                                {{ p.nama_produk }} ({{ p.item_type ?? 'fg' }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Versi" />
                        <TextInput
                            v-model="form.version"
                            class="mt-1 block w-full"
                        />
                    </div>
                    <div>
                        <InputLabel value="Notes" />
                        <TextInput
                            v-model="form.notes"
                            class="mt-1 block w-full"
                        />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel value="Material Items" />
                        <SecondaryButton
                            type="button"
                            class="py-1 text-xs"
                            @click="addItem"
                            >+ Item</SecondaryButton
                        >
                    </div>
                    <div
                        v-for="(item, idx) in form.items"
                        :key="idx"
                        class="mb-2 grid grid-cols-12 items-end gap-2"
                    >
                        <div class="col-span-4">
                            <TextInput
                                v-model="item.material_name"
                                class="block w-full"
                                placeholder="Nama material"
                                required
                            />
                        </div>
                        <div class="col-span-3">
                            <select
                                v-model="item.material_produk_id"
                                class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option value="">— tanpa produk —</option>
                                <option
                                    v-for="p in produks"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.nama_produk }}
                                </option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <TextInput
                                v-model="item.quantity"
                                type="number"
                                step="0.001"
                                min="0"
                                class="block w-full"
                                placeholder="Qty"
                                required
                            />
                        </div>
                        <div class="col-span-2">
                            <select
                                v-model="item.uom_id"
                                class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option value="">UOM</option>
                                <option
                                    v-for="u in uoms"
                                    :key="u.id"
                                    :value="u.id"
                                >
                                    {{ u.code }}
                                </option>
                            </select>
                        </div>
                        <div class="col-span-1">
                            <button
                                type="button"
                                class="text-xs text-red-500"
                                @click="removeItem(idx)"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                    <InputError
                        :message="form.errors.items as string"
                        class="mt-1"
                    />
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showForm = false"
                        >Batal</SecondaryButton
                    >
                    <PrimaryButton :disabled="form.processing"
                        >Simpan</PrimaryButton
                    >
                </div>
            </form>
        </Modal>

        <Modal :show="!!deleteId" @close="deleteId = null">
            <div class="p-6">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Hapus BOM?
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    BOM akan dinonaktifkan dan dihapus.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="deleteId = null"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="deleteId && destroy(deleteId)"
                        >Hapus</DangerButton
                    >
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
