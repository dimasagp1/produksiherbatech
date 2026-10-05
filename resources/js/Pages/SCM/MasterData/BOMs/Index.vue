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
import OdooBomSyncModal from '@/Components/OdooBomSyncModal.vue';
import BomBreakdownModal from '@/Components/BomBreakdownModal.vue';

interface Produk {
    id: number;
    kode_produk: string;
    nama_produk: string;
    item_type?: string;
    odoo_uom?: string;
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
    uom_name?: string | null;
    category?: string | null;
}
interface Bom {
    id: number;
    produk_id?: number;
    odoo_bom_id?: number | null;
    version: string;
    base_qty?: number;
    uom_id?: number | null;
    uom?: Uom | null;
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

const selectedBreakdownBomId = ref<number | null>(null);
const showBreakdownModal = ref(false);

function openBreakdown(bom: Bom) {
    selectedBreakdownBomId.value = bom.id;
    showBreakdownModal.value = true;
}

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
const showBomSyncModal = ref(false);

function syncBoms() {
    showBomSyncModal.value = true;
}

function handleBomSynced() {
    router.reload();
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

            <!-- DESKTOP / TABLET TABLE VIEW (md and up) -->
            <div
                class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 md:block"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs min-w-[700px]">
                        <thead class="bg-gray-50 font-semibold text-gray-600 dark:bg-gray-700/60 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 text-left">
                                    Produk
                                </th>
                                <th class="px-3 py-3 text-left">
                                    Versi & Base Qty
                                </th>
                                <th class="px-3 py-3 text-left">
                                    Komponen Material
                                </th>
                                <th class="px-3 py-3 text-center">
                                    Status
                                </th>
                                <th class="px-4 py-3 text-right">
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <tr v-for="bom in boms.data" :key="bom.id" class="hover:bg-gray-50/70 dark:hover:bg-gray-750/50">
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">
                                            {{ bom.produk?.nama_produk ?? '-' }}
                                        </span>
                                        <div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ bom.produk?.kode_produk ?? '-' }}</span>
                                            <span>•</span>
                                            <span class="uppercase font-semibold text-emerald-600 dark:text-emerald-400">
                                                {{ bom.produk?.item_type ?? 'FG' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-mono font-bold text-gray-800 dark:text-gray-200">
                                            v{{ bom.version }}
                                        </span>
                                        <span class="text-[11px] text-gray-500 dark:text-gray-400 font-mono">
                                            Base: {{ bom.base_qty || 1 }} {{ bom.uom?.code || bom.produk?.odoo_uom || 'Pcs' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="space-y-0.5">
                                        <div
                                            v-for="it in bom.items.slice(0, 3)"
                                            :key="it.id"
                                            class="flex items-center gap-1.5 text-gray-600 dark:text-gray-300"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                            <span>{{ it.material_name }}</span>
                                            <span class="font-mono font-semibold text-gray-800 dark:text-gray-200">
                                                ({{ it.quantity }} {{ it.uom?.code || it.uom_name || '' }})
                                            </span>
                                        </div>
                                        <div v-if="bom.items.length > 3" class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">
                                            + {{ bom.items.length - 3 }} komponen lainnya
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span
                                        :class="
                                            bom.is_active
                                                ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300'
                                                : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'
                                        "
                                        class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase"
                                    >
                                        {{ bom.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="openBreakdown(bom)"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1.5 text-xs font-bold text-emerald-700 shadow-sm transition hover:bg-emerald-100 active:scale-95 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60"
                                            title="Breakdown detail komposisi dan UoM 1 Pcs"
                                        >
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                                            </svg>
                                            <span>Breakdown 1 Pcs</span>
                                        </button>
                                        <button
                                            type="button"
                                            class="font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                                            @click="openEdit(bom)"
                                        >
                                            Edit
                                        </button>
                                        <button
                                            type="button"
                                            class="font-semibold text-red-600 hover:underline dark:text-red-400"
                                            @click="deleteId = bom.id"
                                        >
                                            Hapus
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="boms.data.length === 0">
                                <td
                                    colspan="5"
                                    class="px-3 py-8 text-center text-gray-400"
                                >
                                    Belum ada data BOM
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="boms.data.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-800"
                >
                    Belum ada data BOM.
                </div>

                <div
                    v-for="bom in boms.data"
                    :key="bom.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs dark:border-gray-700 dark:bg-gray-800 space-y-3"
                >
                    <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                        <div>
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm">
                                {{ bom.produk?.nama_produk ?? '-' }}
                            </h4>
                            <div class="flex items-center gap-1.5 text-[11px] text-gray-500 mt-0.5">
                                <span class="font-mono">{{ bom.produk?.kode_produk ?? '-' }}</span>
                                <span>•</span>
                                <span class="uppercase font-semibold text-emerald-600">
                                    {{ bom.produk?.item_type ?? 'FG' }}
                                </span>
                            </div>
                        </div>
                        <span
                            :class="bom.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' : 'bg-gray-100 text-gray-500'"
                            class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase shrink-0"
                        >
                            {{ bom.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Versi BOM</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200">
                                v{{ bom.version }}
                            </span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Base Batch Qty</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200">
                                {{ bom.base_qty || 1 }} {{ bom.uom?.code || bom.produk?.odoo_uom || 'Pcs' }}
                            </span>
                        </div>
                    </div>

                    <!-- Material Components Pill List -->
                    <div class="space-y-1">
                        <span class="text-[10px] font-bold text-gray-500 uppercase tracking-wider">Komponen ({{ bom.items.length }})</span>
                        <div class="flex flex-wrap gap-1">
                            <span
                                v-for="it in bom.items.slice(0, 4)"
                                :key="it.id"
                                class="rounded bg-slate-100 px-2 py-0.5 font-mono text-[10px] text-slate-700 dark:bg-slate-700 dark:text-slate-300"
                            >
                                {{ it.material_name }} ({{ it.quantity }} {{ it.uom?.code || it.uom_name || '' }})
                            </span>
                            <span v-if="bom.items.length > 4" class="text-[10px] font-semibold text-emerald-600 dark:text-emerald-400 self-center">
                                +{{ bom.items.length - 4 }} lagi
                            </span>
                        </div>
                    </div>

                    <!-- Mobile Action Buttons -->
                    <div class="flex items-center justify-between pt-2 border-t border-gray-100 dark:border-gray-700">
                        <button
                            type="button"
                            @click="openBreakdown(bom)"
                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300"
                        >
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <span>Breakdown</span>
                        </button>
                        <div class="flex items-center gap-3 text-xs font-semibold">
                            <button
                                type="button"
                                class="text-indigo-600 hover:underline dark:text-indigo-400"
                                @click="openEdit(bom)"
                            >
                                Edit
                            </button>
                            <button
                                type="button"
                                class="text-red-600 hover:underline dark:text-red-400"
                                @click="deleteId = bom.id"
                            >
                                Hapus
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Mobile Pagination -->
                <div v-if="boms.data.length > 0" class="pt-2">
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

        <!-- Odoo BOM Preview & Selection Modal -->
        <OdooBomSyncModal
            :show="showBomSyncModal"
            @close="showBomSyncModal = false"
            @synced="handleBomSynced"
        />

        <!-- BOM 1 Pcs Composition & Multi-level Breakdown Modal -->
        <BomBreakdownModal
            :show="showBreakdownModal"
            :bom-id="selectedBreakdownBomId"
            @close="showBreakdownModal = false"
        />
    </AuthenticatedLayout>
</template>
