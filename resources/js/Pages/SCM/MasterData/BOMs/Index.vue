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
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-gray-100">
                        Bill of Materials (BOM)
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Standar komposisi material per produk — jadi dasar MRP & Material Usage
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2.5">
                    <SecondaryButton :disabled="syncing" @click="syncBoms" class="gap-1.5">
                        <svg class="h-4 w-4 text-indigo-600 dark:text-indigo-400" :class="{ 'animate-spin': syncing }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        {{ syncing ? 'Menyinkronkan...' : 'Sync BOM Odoo' }}
                    </SecondaryButton>
                    <PrimaryButton @click="openCreate" class="whitespace-nowrap">+ BOM Baru</PrimaryButton>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div v-if="flash.success" class="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 shadow-sm dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-200">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="flex items-center justify-between rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-sm dark:border-rose-800/80 dark:bg-rose-950/40 dark:text-rose-200">
                {{ flash.error }}
            </div>

            <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div class="w-full sm:max-w-md">
                        <InputLabel value="Cari BOM" />
                        <SearchInput v-model="search" placeholder="Cari produk / kode / versi..." class="mt-1.5" @search="goToPage(1)" />
                        <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">Ketik nama produk atau versi, tekan Enter</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-300">Total: {{ boms.total }} BOM</span>
                        <span class="hidden text-xs text-gray-400 sm:block">·</span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">Base Qty = hasil per {{ '1 batch' }}</span>
                    </div>
                </div>
                <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px] text-gray-500 dark:text-gray-400">
                    <span class="rounded-full bg-indigo-50 px-2 py-0.5 font-semibold text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">Dipakai MRP</span>
                    <span>Need = MPS × (Qty / Base Qty)</span>
                    <span class="text-gray-300">·</span>
                    <span>Breakdown 1 Pcs = komposisi mentah + kemasan</span>
                </div>
            </div>

            <!-- DESKTOP / TABLET TABLE VIEW -->
            <div class="hidden overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                        <thead class="bg-gray-50 font-semibold uppercase tracking-wider text-gray-600 dark:bg-gray-900/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 text-[11px]">Produk</th>
                                <th class="px-3 py-3 text-[11px]">Versi & Base Qty</th>
                                <th class="px-3 py-3 text-[11px]">Komponen Material</th>
                                <th class="px-3 py-3 text-center text-[11px]">Status</th>
                                <th class="px-4 py-3 text-right text-[11px]">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60">
                            <tr v-for="bom in boms.data" :key="bom.id" class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40">
                                <td class="px-4 py-3">
                                    <div class="flex flex-col">
                                        <span class="font-bold text-gray-900 dark:text-gray-100">
                                            {{ bom.produk?.nama_produk ?? '-' }}
                                        </span>
                                        <div class="flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                            <span class="font-mono">{{ bom.produk?.kode_produk ?? '-' }}</span>
                                            <span>•</span>
                                            <span class="uppercase font-semibold" :class="bom.produk?.item_type==='rm' ? 'text-amber-600 dark:text-amber-400' : bom.produk?.item_type==='pm' ? 'text-purple-600 dark:text-purple-400' : 'text-emerald-600 dark:text-emerald-400'">
                                                {{ bom.produk?.item_type ?? 'FG' }}
                                            </span>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex flex-col">
                                        <span class="inline-flex w-fit rounded-md bg-gray-900 px-2 py-0.5 font-mono text-xs font-bold text-white dark:bg-gray-700">
                                            v{{ bom.version }}
                                        </span>
                                        <span class="mt-1 font-mono text-xs text-gray-700 dark:text-gray-300">
                                            {{ bom.base_qty || 1 }} {{ bom.uom?.code || bom.produk?.odoo_uom || 'Pcs' }}
                                        </span>
                                        <span class="text-[11px] text-gray-400">per batch</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="space-y-1">
                                        <div v-for="it in bom.items.slice(0, 3)" :key="it.id" class="flex items-center gap-1.5 text-xs text-gray-700 dark:text-gray-300">
                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-emerald-500"></span>
                                            <span class="truncate">{{ it.material_name }}</span>
                                            <span class="shrink-0 font-mono text-[11px] font-semibold text-gray-900 dark:text-gray-100">
                                                {{ it.quantity }} {{ it.uom?.code || it.uom_name || '' }}
                                            </span>
                                        </div>
                                        <div v-if="bom.items.length > 3" class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                            + {{ bom.items.length - 3 }} komponen lainnya
                                        </div>
                                        <div v-if="bom.items.length===0" class="text-[11px] italic text-gray-400">Belum ada komponen</div>
                                    </div>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span :class="bom.is_active ? 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-900/50 dark:text-emerald-300 dark:border-emerald-800' : 'bg-gray-100 text-gray-600 border-gray-200 dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'" class="inline-flex rounded-md border px-2 py-0.5 text-[10px] font-bold uppercase">
                                        {{ bom.is_active ? 'Aktif' : 'Nonaktif' }}
                                    </span>
                                    <div class="mt-1 text-[10px] text-gray-400">{{ bom.is_active ? 'Dipakai MRP' : 'Tidak dipakai' }}</div>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <button type="button" @click="openBreakdown(bom)" class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 hover:bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300 dark:hover:bg-emerald-900/60" title="Lihat komposisi 1 Pcs">
                                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                                            Breakdown
                                        </button>
                                        <button type="button" class="rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/60 dark:text-indigo-300" @click="openEdit(bom)">Edit</button>
                                        <button type="button" class="rounded-md bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300" @click="deleteId = bom.id">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="boms.data.length === 0">
                                <td colspan="5" class="py-12 text-center">
                                    <div class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
                                        <svg class="h-10 w-10 text-gray-300 dark:text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" /></svg>
                                        <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada BOM</p>
                                        <p class="text-xs text-gray-400">Buat BOM pertama atau Sync dari Odoo</p>
                                        <PrimaryButton class="mt-3" @click="openCreate">+ BOM Baru</PrimaryButton>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-gray-200 px-4 py-3 dark:border-gray-700">
                    <Pagination :current-page="boms.current_page" :last-page="boms.last_page" :total="boms.total" :per-page="boms.per_page" @page="goToPage" />
                </div>
            </div>

            <!-- MOBILE CARD VIEW -->
            <div class="block space-y-3 md:hidden">
                <div v-if="boms.data.length === 0" class="rounded-2xl border border-gray-200 bg-white p-6 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">Belum ada BOM</p>
                    <p class="mt-1 text-xs text-gray-400">Buat BOM pertama atau Sync dari Odoo</p>
                </div>

                <div v-for="bom in boms.data" :key="bom.id" class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 space-y-3">
                    <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                        <div class="min-w-0">
                            <h4 class="truncate text-sm font-bold text-gray-900 dark:text-gray-100">
                                {{ bom.produk?.nama_produk ?? '-' }}
                            </h4>
                            <div class="mt-0.5 flex items-center gap-1.5 text-[11px] text-gray-500 dark:text-gray-400">
                                <span class="font-mono">{{ bom.produk?.kode_produk ?? '-' }}</span>
                                <span>•</span>
                                <span class="uppercase font-semibold text-emerald-600 dark:text-emerald-400">{{ bom.produk?.item_type ?? 'FG' }}</span>
                            </div>
                        </div>
                        <span :class="bom.is_active ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'" class="shrink-0 rounded-md px-2 py-0.5 text-[10px] font-bold uppercase">
                            {{ bom.is_active ? 'Aktif' : 'Nonaktif' }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-gray-900/50">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Versi</span>
                            <span class="font-mono text-sm font-bold text-gray-900 dark:text-gray-100">v{{ bom.version }}</span>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-gray-900/50">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Base Qty</span>
                            <span class="font-mono text-sm font-bold text-gray-900 dark:text-gray-100">{{ bom.base_qty || 1 }} {{ bom.uom?.code || bom.produk?.odoo_uom || 'Pcs' }}</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400">Komponen ({{ bom.items.length }})</span>
                        <div class="flex flex-wrap gap-1.5">
                            <span v-for="it in bom.items.slice(0, 4)" :key="it.id" class="rounded-full bg-slate-100 px-2.5 py-1 font-mono text-[11px] font-medium text-slate-700 dark:bg-slate-700 dark:text-slate-300">
                                {{ it.material_name }} · {{ it.quantity }} {{ it.uom?.code || it.uom_name || '' }}
                            </span>
                            <span v-if="bom.items.length > 4" class="self-center rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">+{{ bom.items.length - 4 }} lagi</span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between border-t border-gray-100 pt-3 dark:border-gray-700">
                        <button type="button" @click="openBreakdown(bom)" class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" /></svg>
                            Breakdown
                        </button>
                        <div class="flex items-center gap-2">
                            <button type="button" class="rounded-xl bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300" @click="openEdit(bom)">Edit</button>
                            <button type="button" class="rounded-xl bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300" @click="deleteId = bom.id">Hapus</button>
                        </div>
                    </div>
                </div>

                <div v-if="boms.data.length > 0" class="pt-2">
                    <Pagination :current-page="boms.current_page" :last-page="boms.last_page" :total="boms.total" :per-page="boms.per_page" @page="goToPage" />
                </div>
            </div>
        </div>

        <Modal :show="showForm" @close="showForm = false" max-width="2xl">
            <form class="space-y-4 p-6" @submit.prevent="submit">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ editItem ? 'Edit BOM' : 'BOM Baru' }}
                </h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Base Qty = hasil jadi per resep. Contoh: Base 100 Pcs butuh 5 KG bahan = 0.05 KG/Pcs otomatis saat MRP.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div>
                        <InputLabel value="Produk" />
                        <select v-model="form.produk_id" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" :disabled="!!editItem" required>
                            <option value="" disabled>Pilih produk</option>
                            <option v-for="p in produks" :key="p.id" :value="p.id">{{ p.nama_produk }} ({{ p.item_type ?? 'fg' }})</option>
                        </select>
                    </div>
                    <div>
                        <InputLabel value="Versi" />
                        <TextInput v-model="form.version" class="mt-1 block w-full" />
                    </div>
                    <div>
                        <InputLabel value="Notes" />
                        <TextInput v-model="form.notes" class="mt-1 block w-full" />
                    </div>
                </div>

                <div>
                    <div class="mb-2 flex items-center justify-between">
                        <InputLabel value="Material Items" />
                        <SecondaryButton type="button" class="py-1 text-xs" @click="addItem">+ Item</SecondaryButton>
                    </div>
                    <div v-for="(item, idx) in form.items" :key="idx" class="mb-2 grid grid-cols-12 items-end gap-2">
                        <div class="col-span-4">
                            <TextInput v-model="item.material_name" class="block w-full" placeholder="Nama material" required />
                        </div>
                        <div class="col-span-3">
                            <select v-model="item.material_produk_id" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
                                <option value="">— tanpa produk —</option>
                                <option v-for="p in produks" :key="p.id" :value="p.id">{{ p.nama_produk }}</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <TextInput v-model="item.quantity" type="number" step="0.001" min="0" class="block w-full" placeholder="Qty" required />
                        </div>
                        <div class="col-span-2">
                            <select v-model="item.uom_id" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200">
                                <option value="">UOM</option>
                                <option v-for="u in uoms" :key="u.id" :value="u.id">{{ u.code }}</option>
                            </select>
                        </div>
                        <div class="col-span-1">
                            <button type="button" class="text-xs text-red-500" @click="removeItem(idx)">✕</button>
                        </div>
                    </div>
                    <InputError :message="form.errors.items as string" class="mt-1" />
                </div>

                <div class="flex justify-end gap-2">
                    <SecondaryButton type="button" @click="showForm = false">Batal</SecondaryButton>
                    <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>

        <Modal :show="!!deleteId" @close="deleteId = null">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Hapus BOM?</h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">BOM akan dinonaktifkan dan dihapus.</p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="deleteId = null">Batal</SecondaryButton>
                    <DangerButton @click="deleteId && destroy(deleteId)">Hapus</DangerButton>
                </div>
            </div>
        </Modal>

        <OdooBomSyncModal :show="showBomSyncModal" @close="showBomSyncModal = false" @synced="handleBomSynced" />

        <BomBreakdownModal :show="showBreakdownModal" :bom-id="selectedBreakdownBomId" @close="showBreakdownModal = false" />
    </AuthenticatedLayout>
</template>
