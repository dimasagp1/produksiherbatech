<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
const props = defineProps<{ supply:any }>();
const flash = computed(() => (usePage().props as any).flash ?? {});
const form = useForm({ notes: props.supply.notes ?? '', items: props.supply.items.map((i:any)=> ({ id:i.id, delivered_qty: String(i.delivered_qty ?? 0) })) });
function deliver(){
  router.post(route('scm.supply.deliver', props.supply.id), { notes: form.notes, items: form.items.map((x:any)=> ({ id: x.id, delivered_qty: Number(x.delivered_qty)||0 })) });
}
</script>
<template>
<Head :title="supply.supply_number" />
<AuthenticatedLayout>
<template #header>
<div class="flex items-center justify-between">
<div><h2 class="text-lg font-bold">{{ supply.supply_number }}</h2><p class="text-xs text-gray-500">Required {{ supply.required_date }} · H-1 {{ new Date(new Date(supply.required_date).getTime()-86400000).toLocaleDateString('id-ID') }} · {{ supply.status }}</p></div>
<SecondaryButton @click="router.visit(route('scm.supply.index'))">Kembali</SecondaryButton>
</div>
</template>
<div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
<div v-if="flash.success" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-800">{{ flash.success }}</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800">
<div class="grid grid-cols-2 gap-3 text-xs">
<div>Weekly Plan: <b>{{ supply.weeklyPlan?.batch_number ?? supply.weekly_plan?.batch_number ?? '-' }}</b> {{ supply.weeklyPlan?.produk?.nama_produk ?? '' }}</div>
<div>Work Center: <b>{{ supply.workCenter?.name ?? supply.work_center?.name ?? '-' }}</b></div>
<div>Status: <b>{{ supply.status }}</b> <span v-if="supply.delivered_at">· {{ supply.delivered_at }}</span></div>
<div>Creator: {{ supply.creator?.name ?? '-' }}</div>
</div>
</div>
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800">
<h3 class="text-sm font-bold">Items — isi delivered_qty lalu Mark Delivered</h3>
<table class="mt-2 w-full text-xs">
<thead class="bg-gray-50"><tr><th class="px-2 py-1 text-left">Material</th><th class="px-2 py-1 text-right">Required</th><th class="px-2 py-1 text-right">Delivered</th><th class="px-2 py-1 text-right">Variance</th></tr></thead>
<tbody class="divide-y">
<tr v-for="(it, idx) in supply.items" :key="it.id">
<td class="px-2 py-1">{{ it.produk?.nama_produk ?? it.produk?.kode_produk ?? '-' }} <span class="text-gray-400">({{ it.uom?.code ?? '-' }})</span></td>
<td class="px-2 py-1 text-right font-mono">{{ Number(it.required_qty).toLocaleString('id-ID') }}</td>
<td class="px-2 py-1 text-right"><TextInput v-model="form.items[idx].delivered_qty" type="number" step="0.0001" class="w-24 text-right" /></td>
<td class="px-2 py-1 text-right font-mono" :class="Number(form.items[idx].delivered_qty) < Number(it.required_qty) ? 'text-rose-600' : 'text-emerald-600'">{{ (Number(form.items[idx].delivered_qty) - Number(it.required_qty)).toLocaleString('id-ID') }}</td>
</tr>
</tbody>
</table>
<div class="mt-3">
<label class="text-xs font-semibold">Catatan</label>
<textarea v-model="form.notes" rows="2" class="mt-1 w-full rounded-xl border text-xs"></textarea>
</div>
<div class="mt-3 flex justify-end gap-2">
<SecondaryButton @click="router.visit(route('scm.supply.index'))">Batal</SecondaryButton>
<PrimaryButton @click="deliver">Mark Delivered</PrimaryButton>
</div>
</div>
</div>
</AuthenticatedLayout>
</template>
