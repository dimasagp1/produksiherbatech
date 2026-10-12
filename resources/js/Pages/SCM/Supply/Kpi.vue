<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
defineProps<{ kpi:any, supplies:any[] }>();
</script>
<template>
<Head title="Supply KPI" />
<AuthenticatedLayout>
<template #header><h2 class="text-lg font-bold">Supply KPI — Warehouse H-1</h2><p class="text-xs text-gray-500">On-Time, Complete, Accuracy per supply H-1</p></template>
<div class="mx-auto max-w-7xl space-y-4 px-4 sm:px-6 lg:px-8">
<div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
<div class="rounded-2xl border bg-white p-4 dark:bg-gray-800"><div class="text-xs text-gray-500">On-Time %</div><div class="text-2xl font-extrabold">{{ kpi.onTimePct }}%</div></div>
<div class="rounded-2xl border bg-white p-4 dark:bg-gray-800"><div class="text-xs text-gray-500">Complete %</div><div class="text-2xl font-extrabold">{{ kpi.completePct }}%</div></div>
<div class="rounded-2xl border bg-white p-4 dark:bg-gray-800"><div class="text-xs text-gray-500">Accuracy %</div><div class="text-2xl font-extrabold">{{ kpi.accuracy }}%</div></div>
<div class="rounded-2xl border bg-white p-4 dark:bg-gray-800"><div class="text-xs text-gray-500">Late</div><div class="text-2xl font-extrabold text-rose-600">{{ kpi.late }} / {{ kpi.total }}</div></div>
</div>
<div class="overflow-auto rounded-2xl border bg-white dark:bg-gray-800">
<table class="w-full text-xs"><thead class="bg-gray-50"><tr><th class="px-3 py-2">Supply</th><th class="px-3 py-2">Required</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Work Center</th></tr></thead><tbody class="divide-y"><tr v-for="s in supplies" :key="s.id"><td class="px-3 py-2 font-mono">{{ s.supply_number }}</td><td class="px-3 py-2">{{ s.required_date }}</td><td class="px-3 py-2">{{ s.status }}</td><td class="px-3 py-2">{{ s.workCenter?.name ?? s.work_center?.name ?? '-' }}</td></tr></tbody></table>
</div>
</div>
</AuthenticatedLayout>
</template>
