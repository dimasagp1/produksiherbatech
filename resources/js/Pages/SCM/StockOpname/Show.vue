<script setup lang="ts">
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';

interface Item {
    id: number;
    produk_id: number;
    batch_number?: string | null;
    system_qty: number;
    counted_qty?: number | null;
    discrepancy?: number | null;
    stelling_card?: string | null;
    discrepancy_reason?: string | null;
    produk?: {
        nama_produk?: string;
        kode_produk?: string;
        item_type?: string;
        odoo_uom?: string;
        uom?: { code?: string } | null;
    } | null;
}
interface Opname {
    id: number;
    opname_number: string;
    status: string;
    location: string;
    ira_persen?: number | null;
    discrepancy_value_rate?: number | null;
    notes?: string | null;
    initiator?: { name?: string } | null;
    approver?: { name?: string } | null;
}

const props = defineProps<{
    opname: Opname;
    items: Item[];
    typeBreakdown?: {
        all: { total: number; counted: number; accurate: number };
        fg: { total: number; counted: number; accurate: number };
        rm: { total: number; counted: number; accurate: number };
        pm: { total: number; counted: number; accurate: number };
        wip: { total: number; counted: number; accurate: number };
    };
    metrics: {
        ira: number | null;
        ira_target: number;
        discrepancy_value_rate: number;
        counted_count: number;
        accurate_count: number;
        total_items: number;
        coa: string[];
    };
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const role = computed(() => (usePage().props as any).auth?.user?.role ?? '');
const canInput = computed(() =>
    ['warehouse_admin', 'superadmin', 'admin'].includes(role.value),
);
const canApprove = computed(() =>
    ['manager', 'superadmin'].includes(role.value),
);

const activeTypeFilter = ref<string>('all');

const filteredItems = computed(() => {
    if (activeTypeFilter.value === 'all') {
        return props.items;
    }
    return props.items.filter(
        (it) => (it.produk?.item_type || 'fg').toLowerCase() === activeTypeFilter.value,
    );
});

function itemTypeBadge(type?: string): { label: string; cls: string } {
    const t = (type || 'fg').toLowerCase();
    switch (t) {
        case 'rm':
            return { label: 'Raw Material (RM)', cls: 'bg-amber-100 text-amber-800 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800' };
        case 'pm':
            return { label: 'Packaging (PM)', cls: 'bg-purple-100 text-purple-800 border-purple-200 dark:bg-purple-950/50 dark:text-purple-300 dark:border-purple-800' };
        case 'wip':
            return { label: 'WIP', cls: 'bg-sky-100 text-sky-800 border-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800' };
        case 'fg':
        default:
            return { label: 'Finished Good (FG)', cls: 'bg-emerald-100 text-emerald-800 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800' };
    }
}

const counted = reactive<
    Record<number, { counted_qty: string; discrepancy_reason: string }>
>({});
watch(
    () => props.items,
    (items) => {
        items.forEach((it) => {
            counted[it.id] = {
                counted_qty:
                    it.counted_qty !== null && it.counted_qty !== undefined
                        ? String(it.counted_qty)
                        : '',
                discrepancy_reason: it.discrepancy_reason ?? '',
            };
        });
    },
    { immediate: true },
);

const canEdit = computed(() =>
    ['initiated', 'counted'].includes(props.opname.status),
);

function saveItems() {
    router.put(
        route('scm.stock-opname.items', props.opname.id),
        {
            items: Object.entries(counted)
                .filter(([, v]) => v.counted_qty !== '')
                .map(([id, v]) => ({
                    id: Number(id),
                    counted_qty: Number(v.counted_qty),
                    discrepancy_reason: v.discrepancy_reason || null,
                })),
        },
        { preserveScroll: true },
    );
}

function recalculate() {
    router.post(
        route('scm.stock-opname.recalculate', props.opname.id),
        {},
        { preserveScroll: true },
    );
}

function postAdjustment() {
    router.post(
        route('scm.stock-opname.post-adjustment', props.opname.id),
        {},
        { preserveScroll: true },
    );
}

function approve() {
    router.post(
        route('scm.stock-opname.approve', props.opname.id),
        {},
        { preserveScroll: true },
    );
}

const statusColor: Record<string, string> = {
    initiated: 'bg-gray-100 text-gray-700',
    counted: 'bg-sky-100 text-sky-800',
    recalculated: 'bg-indigo-100 text-indigo-800',
    posted: 'bg-amber-100 text-amber-800',
    approved: 'bg-emerald-100 text-emerald-800',
    cancelled: 'bg-red-100 text-red-800',
};
</script>

<template>
    <Head :title="opname.opname_number" />
    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        {{ opname.opname_number }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ opname.location }} ·
                        <span
                            :class="statusColor[opname.status]"
                            class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase"
                            >{{ opname.status }}</span
                        >
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('scm.stock-opname.index')"
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300"
                        >Kembali</Link
                    >
                    <a
                        :href="
                            route(
                                'scm.stock-opname.print-kartu-stelling',
                                opname.id,
                            )
                        "
                        class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300"
                        >Cetak Kartu Stelling</a
                    >
                    <SecondaryButton
                        v-if="canEdit && canInput"
                        class="py-1.5 text-xs"
                        @click="saveItems"
                        >Simpan Hitung</SecondaryButton
                    >
                    <PrimaryButton
                        v-if="props.opname.status === 'counted' && canInput"
                        class="py-1.5 text-xs"
                        @click="recalculate"
                        >Recalculate</PrimaryButton
                    >
                    <PrimaryButton
                        v-if="
                            props.opname.status === 'recalculated' && canInput
                        "
                        class="py-1.5 text-xs"
                        @click="postAdjustment"
                        >Post Adjustment</PrimaryButton
                    >
                    <PrimaryButton
                        v-if="props.opname.status === 'posted' && canApprove"
                        class="py-1.5 text-xs"
                        @click="approve"
                        >Approve</PrimaryButton
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

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">
                        IRA (target ≥ {{ metrics.ira_target }}%)
                    </div>
                    <div
                        class="text-xl font-bold"
                        :class="
                            (metrics.ira ?? 0) >= metrics.ira_target
                                ? 'text-emerald-600'
                                : 'text-red-600'
                        "
                    >
                        {{ metrics.ira ?? '-' }}%
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">
                        Discrepancy Value Rate
                    </div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ metrics.discrepancy_value_rate }}%
                    </div>
                    <div class="text-[10px] text-gray-400">
                        COA: {{ metrics.coa.join(' / ') }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Akurat / Dihitung</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ metrics.accurate_count }}/{{ metrics.counted_count }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Total Baris</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ metrics.total_items }}
                    </div>
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex flex-wrap items-center gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
                <button
                    type="button"
                    @click="activeTypeFilter = 'all'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeTypeFilter === 'all'
                            ? 'bg-indigo-600 text-white shadow-sm dark:bg-indigo-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    Semua Item
                    <span
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="activeTypeFilter === 'all' ? 'bg-indigo-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ typeBreakdown?.all?.total ?? items.length }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="activeTypeFilter = 'fg'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeTypeFilter === 'fg'
                            ? 'bg-emerald-600 text-white shadow-sm dark:bg-emerald-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-400"></span>
                    Finished Goods (FG)
                    <span
                        v-if="typeBreakdown?.fg"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="activeTypeFilter === 'fg' ? 'bg-emerald-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ typeBreakdown.fg.total }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="activeTypeFilter = 'rm'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeTypeFilter === 'rm'
                            ? 'bg-amber-600 text-white shadow-sm dark:bg-amber-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-amber-400"></span>
                    Raw Material (RM / Bahan Baku)
                    <span
                        v-if="typeBreakdown?.rm"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="activeTypeFilter === 'rm' ? 'bg-amber-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ typeBreakdown.rm.total }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="activeTypeFilter = 'pm'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeTypeFilter === 'pm'
                            ? 'bg-purple-600 text-white shadow-sm dark:bg-purple-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-purple-400"></span>
                    Packaging Material (PM / Kemas)
                    <span
                        v-if="typeBreakdown?.pm"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="activeTypeFilter === 'pm' ? 'bg-purple-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ typeBreakdown.pm.total }}
                    </span>
                </button>
                <button
                    type="button"
                    @click="activeTypeFilter = 'wip'"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-xs font-semibold transition"
                    :class="
                        activeTypeFilter === 'wip'
                            ? 'bg-sky-600 text-white shadow-sm dark:bg-sky-500'
                            : 'bg-white text-gray-600 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                    "
                >
                    <span class="inline-block h-2 w-2 rounded-full bg-sky-400"></span>
                    WIP
                    <span
                        v-if="typeBreakdown?.wip"
                        class="rounded-full px-1.5 py-0.2 text-[10px]"
                        :class="activeTypeFilter === 'wip' ? 'bg-sky-700 text-white' : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300'"
                    >
                        {{ typeBreakdown.wip.total }}
                    </span>
                </button>
            </div>

            <!-- DESKTOP / TABLET TABLE VIEW (md and up) -->
            <div
                class="hidden overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 md:block"
            >
                <div class="overflow-x-auto">
                    <table class="w-full text-xs min-w-[950px]">
                        <thead class="bg-gray-50 dark:bg-gray-700/60">
                            <tr>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    ST Card
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Produk / Material
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Kategori
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Batch
                                </th>
                                <th
                                    class="px-3 py-2 text-right font-semibold text-gray-500"
                                >
                                    Saldo Odoo (Buku)
                                </th>
                                <th
                                    class="px-3 py-2 text-right font-semibold text-gray-500"
                                >
                                    Hitung Fisik (IC)
                                </th>
                                <th
                                    class="px-3 py-2 text-right font-semibold text-gray-500"
                                >
                                    Selisih
                                </th>
                                <th
                                    class="px-3 py-2 text-left font-semibold text-gray-500"
                                >
                                    Alasan / Catatan IC
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-100 dark:divide-gray-700"
                        >
                            <tr v-for="it in filteredItems" :key="it.id" class="hover:bg-gray-50/70 dark:hover:bg-gray-700/40">
                                <td
                                    class="px-3 py-2 font-mono text-[11px] text-gray-500"
                                >
                                    {{ it.stelling_card }}
                                </td>
                                <td
                                    class="px-3 py-2"
                                >
                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ it.produk?.nama_produk ?? '-' }}
                                    </div>
                                    <div class="text-[10px] font-mono text-gray-500">
                                        {{ it.produk?.kode_produk ?? '' }}
                                    </div>
                                </td>
                                <td class="px-3 py-2">
                                    <span
                                        :class="itemTypeBadge(it.produk?.item_type).cls"
                                        class="inline-block rounded-md border px-1.5 py-0.5 text-[9px] font-semibold"
                                    >
                                        {{ itemTypeBadge(it.produk?.item_type).label }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 font-mono text-gray-600 dark:text-gray-300">
                                    {{ it.batch_number ?? '-' }}
                                </td>
                                <td class="px-3 py-2 text-right font-mono font-semibold text-gray-800 dark:text-gray-200">
                                    {{ Number(it.system_qty).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 3 }) }}
                                    <span class="text-[10px] font-normal text-gray-500 ml-0.5">{{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? '' }}</span>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <template v-if="canEdit && canInput">
                                        <div class="flex items-center justify-end gap-1">
                                            <TextInput
                                                v-model="counted[it.id].counted_qty"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                placeholder="Input fisik"
                                                class="block w-28 text-right font-bold text-indigo-600 dark:text-indigo-400"
                                            />
                                            <span class="text-[10px] text-gray-500 font-mono">{{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? '' }}</span>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <span class="font-mono font-bold">{{ it.counted_qty ?? '-' }}</span>
                                        <span v-if="it.counted_qty !== null" class="text-[10px] text-gray-500 ml-0.5">{{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? '' }}</span>
                                    </template>
                                </td>
                                <td
                                    class="px-3 py-2 text-right font-mono font-bold"
                                    :class="
                                        (it.discrepancy ?? 0) !== 0
                                            ? 'text-red-600'
                                            : 'text-emerald-600'
                                    "
                                >
                                    {{
                                        it.discrepancy ??
                                        (counted[it.id]?.counted_qty
                                            ? (
                                                  Number(
                                                      counted[it.id].counted_qty,
                                                  ) - Number(it.system_qty)
                                              ).toFixed(2)
                                            : '-')
                                    }}
                                </td>
                                <td class="px-3 py-2">
                                    <TextInput
                                        v-if="canEdit && canInput"
                                        v-model="counted[it.id].discrepancy_reason"
                                        class="block w-full text-xs"
                                        placeholder="Keterangan selisih / temuan IC"
                                    />
                                    <span v-else class="text-gray-600 dark:text-gray-300">{{
                                        it.discrepancy_reason ?? '-'
                                    }}</span>
                                </td>
                            </tr>
                            <tr v-if="filteredItems.length === 0">
                                <td
                                    colspan="8"
                                    class="px-3 py-8 text-center text-gray-400"
                                >
                                    Tidak ada item dalam kategori ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="filteredItems.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-6 text-center text-xs text-gray-400 dark:border-gray-700 dark:bg-gray-800"
                >
                    Tidak ada item dalam kategori ini.
                </div>

                <div
                    v-for="it in filteredItems"
                    :key="it.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-2xs dark:border-gray-700 dark:bg-gray-800 space-y-3"
                >
                    <div class="flex items-start justify-between gap-2 border-b border-gray-100 pb-2.5 dark:border-gray-700">
                        <div>
                            <span class="font-mono text-[11px] text-gray-500">
                                {{ it.stelling_card }}
                            </span>
                            <h4 class="font-bold text-gray-900 dark:text-gray-100 text-xs mt-0.5">
                                {{ it.produk?.nama_produk ?? '-' }}
                            </h4>
                            <div class="text-[10px] font-mono text-gray-400">
                                {{ it.produk?.kode_produk ?? '' }} · Batch: <span class="text-gray-700 dark:text-gray-300">{{ it.batch_number ?? '-' }}</span>
                            </div>
                        </div>
                        <span
                            :class="itemTypeBadge(it.produk?.item_type).cls"
                            class="inline-block rounded-md border px-1.5 py-0.5 text-[9px] font-semibold shrink-0"
                        >
                            {{ itemTypeBadge(it.produk?.item_type).label }}
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Saldo Odoo (Buku)</span>
                            <span class="font-mono font-bold text-gray-800 dark:text-gray-200">
                                {{ Number(it.system_qty).toLocaleString('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 3 }) }}
                                <span class="text-[10px] font-normal text-gray-500">{{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? '' }}</span>
                            </span>
                        </div>
                        <div class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/50">
                            <span class="block text-[10px] text-gray-500">Selisih Fisik</span>
                            <span
                                class="font-mono font-bold"
                                :class="(it.discrepancy ?? 0) !== 0 ? 'text-red-600 dark:text-red-400' : 'text-emerald-600 dark:text-emerald-400'"
                            >
                                {{
                                    it.discrepancy ??
                                    (counted[it.id]?.counted_qty
                                        ? (
                                              Number(
                                                  counted[it.id].counted_qty,
                                              ) - Number(it.system_qty)
                                          ).toFixed(2)
                                        : '-')
                                }}
                            </span>
                        </div>
                    </div>

                    <!-- Input Fisik & Reason di Mobile -->
                    <div v-if="canEdit && canInput" class="space-y-2 pt-1 border-t border-gray-100 dark:border-gray-700">
                        <div>
                            <label class="block text-[10px] font-bold text-gray-600 dark:text-gray-400 mb-1">
                                Input Hitung Fisik ({{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? 'Pcs' }})
                            </label>
                            <TextInput
                                v-model="counted[it.id].counted_qty"
                                type="number"
                                step="0.001"
                                min="0"
                                placeholder="0.00"
                                class="block w-full text-xs font-bold text-indigo-600 dark:text-indigo-400"
                            />
                        </div>
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-500 mb-1">Catatan / Alasan IC</label>
                            <TextInput
                                v-model="counted[it.id].discrepancy_reason"
                                class="block w-full text-xs"
                                placeholder="Alasan selisih..."
                            />
                        </div>
                    </div>
                    <div v-else class="text-xs pt-1 flex items-center justify-between text-gray-500">
                        <span>Fisik: <strong class="text-gray-800 dark:text-gray-200">{{ it.counted_qty ?? '-' }} {{ it.produk?.uom?.code ?? it.produk?.odoo_uom ?? '' }}</strong></span>
                        <span v-if="it.discrepancy_reason" class="text-[11px] italic text-gray-400">{{ it.discrepancy_reason }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
