<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

interface Item {
    id: number;
    material_name: string;
    quantity_used: number;
    quantity_standard: number;
    variance: number;
    ratio_persen?: number | null;
    uom?: { code?: string } | null;
}
interface Usage {
    id: number;
    usage_number: string;
    usage_date: string;
    shift?: string | null;
    notes?: string | null;
    weekly_plan?: {
        batch_number: string;
        produk?: { nama_produk: string };
    } | null;
    user?: { name: string };
}

const props = defineProps<{
    usage: Usage;
    items: Item[];
    summary: {
        total_standard: number;
        total_used: number;
        total_variance: number;
        overall_ratio: number | null;
        ratio_target: number;
    };
}>();
</script>

<template>
    <Head title="Detail Material Usage" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        {{ usage.usage_number }}
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        {{ usage.usage_date }} ·
                        {{ usage.weekly_plan?.produk?.nama_produk ?? '-' }} ·
                        Batch {{ usage.weekly_plan?.batch_number ?? '-' }}
                    </p>
                </div>
                <Link
                    :href="route('scm.material-usage.index')"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300"
                    >Kembali</Link
                >
            </div>
        </template>

        <div class="mx-auto max-w-5xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Total Standard</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ summary.total_standard }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Total Actual</div>
                    <div
                        class="text-xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ summary.total_used }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">Variance</div>
                    <div
                        class="text-xl font-bold"
                        :class="
                            summary.total_variance > 0
                                ? 'text-red-600'
                                : 'text-emerald-600'
                        "
                    >
                        {{ summary.total_variance }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500">
                        Ratio (target ≤ {{ summary.ratio_target }}%)
                    </div>
                    <div
                        class="text-xl font-bold"
                        :class="
                            summary.overall_ratio !== null &&
                            summary.overall_ratio > summary.ratio_target
                                ? 'text-red-600'
                                : 'text-emerald-600'
                        "
                    >
                        {{ summary.overall_ratio ?? '-' }}%
                    </div>
                </div>
            </div>

            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
            >
                <table class="w-full text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/60">
                        <tr>
                            <th
                                class="px-3 py-2 text-left font-semibold text-gray-500"
                            >
                                Material
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Standard
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Actual
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Variance
                            </th>
                            <th
                                class="px-3 py-2 text-right font-semibold text-gray-500"
                            >
                                Ratio %
                            </th>
                        </tr>
                    </thead>
                    <tbody
                        class="divide-y divide-gray-100 dark:divide-gray-700"
                    >
                        <tr v-for="it in items" :key="it.id">
                            <td
                                class="px-3 py-2 font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ it.material_name }}
                                <span class="text-gray-400">{{
                                    it.uom?.code ?? ''
                                }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ it.quantity_standard }}
                            </td>
                            <td class="px-3 py-2 text-right font-mono">
                                {{ it.quantity_used }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-mono"
                                :class="
                                    it.variance > 0
                                        ? 'text-red-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ it.variance }}
                            </td>
                            <td
                                class="px-3 py-2 text-right font-bold"
                                :class="
                                    it.ratio_persen != null &&
                                    it.ratio_persen > summary.ratio_target
                                        ? 'text-red-600'
                                        : 'text-emerald-600'
                                "
                            >
                                {{ it.ratio_persen ?? '-' }}%
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
