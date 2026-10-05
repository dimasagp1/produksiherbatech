<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Create from './Create.vue';

interface Scrap {
    id: number;
    batch_number: string;
    material_name: string;
    quantity: number;
    source: string;
    defect_reason?: string | null;
    odoo_scrap_id?: number | null;
    produk?: { nama_produk?: string } | null;
    uom?: { code?: string } | null;
    creator?: { name?: string } | null;
    created_at?: string;
}
interface Plan {
    id: number;
    batch_number: string;
    produk?: { id: number; nama_produk: string };
}
interface Material {
    id: number;
    nama_produk: string;
}
interface Uom {
    id: number;
    code: string;
    name: string;
}

const props = defineProps<{
    scraps: {
        data: Scrap[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    source: string | null;
    plans?: Plan[];
    materials?: Material[];
    uoms?: Uom[];
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.search ?? '');
const source = ref(props.source ?? '');
const showCreate = ref(false);
const deleteId = ref<number | null>(null);
const pulling = ref(false);

function applyFilter() {
    router.get(
        route('scm.scrap-material.index'),
        {
            search: search.value || undefined,
            source: source.value || undefined,
        },
        { preserveState: true },
    );
}

function pullOdoo() {
    pulling.value = true;
    router.post(
        route('scm.scrap-material.pull-odoo'),
        {},
        {
            onFinish: () => {
                pulling.value = false;
            },
        },
    );
}

function submitManual(data: Record<string, any>) {
    router.post(route('scm.scrap-material.store'), data as never, {
        onSuccess: () => {
            showCreate.value = false;
        },
    });
}

function destroy() {
    if (!deleteId.value) return;
    router.delete(route('scm.scrap-material.destroy', deleteId.value), {
        onSuccess: () => {
            deleteId.value = null;
        },
    });
}

function goToPage(page: number) {
    router.get(
        route('scm.scrap-material.index'),
        { page, search: search.value, source: source.value || undefined },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Scrap Material" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Scrap Material
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Input BOM (label/botol/bahan) — terpisah dari Reject
                        Produk (output)
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton
                        class="py-1.5 text-xs"
                        :disabled="pulling"
                        @click="pullOdoo"
                        >{{
                            pulling ? 'Pull...' : 'Pull Odoo'
                        }}</SecondaryButton
                    >
                    <PrimaryButton
                        class="py-1.5 text-xs"
                        @click="showCreate = true"
                        >+ Manual</PrimaryButton
                    >
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
            <div
                v-if="flash.error"
                class="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200"
            >
                {{ flash.error }}
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <input
                    v-model="search"
                    type="text"
                    placeholder="Cari batch / material..."
                    class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @keyup.enter="applyFilter"
                />
                <select
                    v-model="source"
                    class="rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @change="applyFilter"
                >
                    <option value="">Semua sumber</option>
                    <option value="odoo">Odoo</option>
                    <option value="manual">Manual</option>
                </select>
                <button
                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white"
                    @click="applyFilter"
                >
                    Filter
                </button>
            </div>

            <!-- DESKTOP / TABLET TABLE VIEW (md and up) -->
            <div
                class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 md:block"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs min-w-[650px]">
                        <thead class="bg-gray-50 dark:bg-gray-700/60">
                            <tr>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Batch
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Material
                                </th>
                                <th
                                    class="px-3 py-2 text-right font-semibold text-gray-500"
                                >
                                    Qty
                                </th>
                                <th
                                    class="px-3 py-2 text-center font-semibold text-gray-500"
                                >
                                    Sumber
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Alasan
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
                            <tr v-for="s in scraps.data" :key="s.id" class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40">
                                <td
                                    class="px-3 py-2.5 font-mono font-semibold text-gray-900 dark:text-gray-100"
                                >
                                    {{ s.batch_number }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ s.material_name }}
                                    </div>
                                    <span class="text-[11px] text-gray-400 font-mono">{{
                                        s.uom?.code ?? ''
                                    }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-right font-mono font-bold text-gray-900 dark:text-gray-100">
                                    {{ s.quantity }}
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span
                                        :class="
                                            s.source === 'odoo'
                                                ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300'
                                                : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'
                                        "
                                        class="rounded px-2 py-0.5 text-[10px] font-bold uppercase"
                                        >{{ s.source }}</span
                                    >
                                    <span
                                        v-if="s.odoo_scrap_id"
                                        class="ml-1 text-[10px] text-gray-400 font-mono"
                                        >#{{ s.odoo_scrap_id }}</span
                                    >
                                </td>
                                <td
                                    class="px-3 py-2.5 text-gray-600 dark:text-gray-300"
                                >
                                    {{ s.defect_reason ?? '-' }}
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <button
                                        v-if="s.source === 'manual'"
                                        class="font-semibold text-red-600 hover:underline dark:text-red-400"
                                        @click="deleteId = s.id"
                                    >
                                        Hapus
                                    </button>
                                    <span v-else class="text-[10px] text-gray-400 font-mono"
                                        >Odoo-locked</span
                                    >
                                </td>
                            </tr>
                            <tr v-if="scraps.data.length === 0">
                                <td
                                    colspan="6"
                                    class="px-3 py-8 text-center text-gray-400"
                                >
                                    Belum ada scrap material
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="scraps.current_page"
                        :last-page="scraps.last_page"
                        :total="scraps.total"
                        :per-page="scraps.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="scraps.data.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-800"
                >
                    Belum ada scrap material.
                </div>

                <div
                    v-for="s in scraps.data"
                    :key="s.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs dark:border-gray-700 dark:bg-gray-800 space-y-3"
                >
                    <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                        <div>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-gray-700 dark:bg-gray-700 dark:text-gray-300">
                                Batch: {{ s.batch_number }}
                            </span>
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 text-xs mt-1">
                                {{ s.material_name }}
                            </h4>
                        </div>
                        <span
                            :class="s.source === 'odoo' ? 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300'"
                            class="rounded px-2 py-0.5 text-[9px] font-bold uppercase shrink-0"
                        >
                            {{ s.source }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Qty Scrap</span>
                            <span class="font-mono font-bold text-rose-600 dark:text-rose-400">
                                {{ s.quantity }} <span class="text-[10px] text-gray-500 font-normal">{{ s.uom?.code ?? '' }}</span>
                            </span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Alasan Defect</span>
                            <span class="text-gray-700 dark:text-gray-300 truncate block">
                                {{ s.defect_reason || '-' }}
                            </span>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1 text-xs">
                        <span v-if="s.odoo_scrap_id" class="text-[10px] text-gray-400 font-mono">
                            Odoo Ref: #{{ s.odoo_scrap_id }}
                        </span>
                        <span v-else class="text-[10px] text-gray-400">Manual Entry</span>

                        <div>
                            <button
                                v-if="s.source === 'manual'"
                                class="rounded bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:bg-rose-950/40 dark:text-rose-300"
                                @click="deleteId = s.id"
                            >
                                Hapus
                            </button>
                            <span v-else class="text-[10px] text-gray-400 font-mono">
                                Odoo-locked
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Mobile Pagination -->
                <div v-if="scraps.data.length > 0" class="pt-2">
                    <Pagination
                        :current-page="scraps.current_page"
                        :last-page="scraps.last_page"
                        :total="scraps.total"
                        :per-page="scraps.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>

        <Modal :show="showCreate" @close="showCreate = false" max-width="lg">
            <Create
                :plans="plans ?? []"
                :materials="materials ?? []"
                :uoms="uoms ?? []"
                @submit="submitManual"
                @close="showCreate = false"
            />
        </Modal>

        <Modal :show="!!deleteId" @close="deleteId = null">
            <div class="p-6">
                <h3
                    class="text-lg font-semibold text-gray-900 dark:text-gray-100"
                >
                    Hapus scrap manual?
                </h3>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    Stok material akan dikembalikan.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <SecondaryButton @click="deleteId = null"
                        >Batal</SecondaryButton
                    >
                    <DangerButton @click="destroy">Hapus</DangerButton>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
