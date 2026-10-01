<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Item {
    id: number;
    material_name: string;
    quantity_used: number;
    quantity_standard: number;
    variance: number;
    ratio_persen?: number | null;
}
interface Usage {
    id: number;
    usage_number: string;
    usage_date: string;
    shift?: string | null;
    weekly_plan?: {
        batch_number: string;
        produk?: { nama_produk: string };
    } | null;
    user?: { name: string };
    items: Item[];
}

const props = defineProps<{
    usages: {
        data: Usage[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search: string;
    ratioTarget: number;
}>();

const flash = computed(() => (usePage().props as any).flash ?? {});
const search = ref(props.search);

function ratioColor(r: number | null | undefined): string {
    if (r === null || r === undefined) return 'text-gray-400';
    if (r <= props.ratioTarget) return 'text-emerald-600 dark:text-emerald-400';
    return 'text-red-600 dark:text-red-400';
}

function overallRatio(u: Usage): number | null {
    const std = u.items.reduce(
        (s, i) => s + Number(i.quantity_standard || 0),
        0,
    );
    const used = u.items.reduce((s, i) => s + Number(i.quantity_used || 0), 0);
    return std > 0 ? Number((((used - std) / std) * 100).toFixed(2)) : null;
}

const syncing = ref(false);

function syncUsages() {
    syncing.value = true;
    router.post(
        route('scm.material-usage.sync-odoo'),
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
        route('scm.material-usage.index'),
        { page, search: search.value },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="Material Usage" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Material Usage
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Ratio target ≤ {{ ratioTarget }}% · Sinkron dari Odoo MO / BOM aktif
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        :disabled="syncing"
                        @click="syncUsages"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-sm transition hover:bg-gray-50 active:scale-95 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
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
                        <span>{{ syncing ? 'Menyinkronkan...' : 'Sync Pemakaian Odoo' }}</span>
                    </button>
                    <a
                        :href="route('scm.material-usage.export', 'pdf')"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Export PDF
                    </a>
                    <a
                        :href="route('scm.material-usage.export', 'excel')"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >
                        Export Excel
                    </a>
                    <Link
                        :href="route('scm.material-usage.create')"
                        class="rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                    >
                        + Input Usage
                    </Link>
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
                    placeholder="Cari no usage / batch..."
                    class="max-w-md"
                    @search="goToPage(1)"
                />
                <span class="text-xs text-gray-500"
                    >Total: {{ usages.total }}</span
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
                                No. Usage
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Tanggal
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Batch / Produk
                            </th>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Items
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Ratio %
                            </th>
                            <th
                                class="px-3 py-2 text-center font-semibold text-gray-500"
                            >
                                Aksi
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="u in usages.data" :key="u.id">
                            <td
                                class="px-3 py-2 font-mono font-semibold text-gray-900 dark:text-gray-100"
                            >
                                {{ u.usage_number }}
                            </td>
                            <td class="px-3 py-2">{{ u.usage_date }}</td>
                            <td class="px-3 py-2">
                                <div
                                    class="font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        u.weekly_plan?.produk?.nama_produk ??
                                        '-'
                                    }}
                                </div>
                                <div
                                    class="font-mono text-[11px] text-gray-500"
                                >
                                    {{ u.weekly_plan?.batch_number ?? '-' }}
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <div
                                    v-for="it in u.items"
                                    :key="it.id"
                                    class="text-gray-600 dark:text-gray-300"
                                >
                                    {{ it.material_name }}:
                                    {{ it.quantity_used }}/{{
                                        it.quantity_standard
                                    }}
                                    <span :class="ratioColor(it.ratio_persen)"
                                        >({{ it.ratio_persen ?? '-' }}%)</span
                                    >
                                </div>
                            </td>
                            <td
                                class="px-3 py-2 text-right font-bold"
                                :class="ratioColor(overallRatio(u))"
                            >
                                {{ overallRatio(u) ?? '-' }}%
                            </td>
                            <td class="px-3 py-2 text-center">
                                <Link
                                    :href="
                                        route('scm.material-usage.show', u.id)
                                    "
                                    class="font-semibold text-indigo-600 hover:underline"
                                    >Detail</Link
                                >
                            </td>
                        </tr>
                        <tr v-if="usages.data.length === 0">
                            <td
                                colspan="6"
                                class="px-3 py-8 text-center text-gray-400"
                            >
                                Belum ada material usage
                            </td>
                        </tr>
                    </tbody>
                </table>
                <div
                    class="border-t border-gray-200 px-4 py-2 dark:border-gray-700"
                >
                    <Pagination
                        :current-page="usages.current_page"
                        :last-page="usages.last_page"
                        :total="usages.total"
                        :per-page="usages.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
