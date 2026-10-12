<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps<{ weeklyPlans:any[], workCenters:any[], produks:any[], uoms:any[], preselected:any }>();
const form = useForm({
  weekly_plan_id: props.preselected?.weeklyPlan?.id ? String(props.preselected.weeklyPlan.id) : '',
  work_center_id: '',
  required_date: '',
  notes: '',
  items: [{ produk_id:'', required_qty:'', uom_id:'' }] as any[],
});

function loadWeeklyPlan(id:string){
  if(!id) return;
  router.get(route('scm.supply.create'), { weekly_plan_id: id }, { preserveState:true, only:['preselected'] as any });
}

watch(()=> (props.preselected as any)?.bom, (bom)=>{
  if(!bom) return;
  const all = [...(bom.raw_materials??[]), ...(bom.primary_packaging??[]), ...(bom.secondary_packaging??[])];
  form.items = all.slice(0,12).map((m:any)=> ({ produk_id: String(m.material_produk_id ?? ''), required_qty: String(m.quantity ?? 0), uom_id: '' }));
  if(!form.work_center_id && props.preselected?.weeklyPlan?.work_center_id) form.work_center_id = String(props.preselected.weeklyPlan.work_center_id);
  if(!form.required_date && props.preselected?.weeklyPlan?.tanggal) form.required_date = props.preselected.weeklyPlan.tanggal;
}, { immediate:true });

function addItem(){ form.items.push({ produk_id:'', required_qty:'', uom_id:'' }); }
function removeItem(i:number){ if(form.items.length>1) form.items.splice(i,1); }
function submit(){ form.post(route('scm.supply.store')); }
</script>
<template>
<Head title="Buat Supply H-1" />
<AuthenticatedLayout>
<template #header>
<div class="flex items-center justify-between">
<div><h2 class="text-lg font-bold">Buat Supply H-1</h2><p class="text-xs text-gray-500">Auto-populate dari Weekly Plan BOM · target kirim H-1 sebelum MO</p></div>
<SecondaryButton @click="router.visit(route('scm.supply.index'))">Kembali</SecondaryButton>
</div>
</template>
<div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
<div class="rounded-2xl border bg-white p-4 shadow-sm dark:bg-gray-800">
<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
<div>
<InputLabel value="Weekly Plan (opsional)" />
<select v-model="form.weekly_plan_id" @change="loadWeeklyPlan(form.weekly_plan_id)" class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs dark:bg-gray-900">
<option value="">— tanpa WP —</option>
<option v-for="wp in weeklyPlans" :key="wp.id" :value="wp.id">{{ wp.batch_number }} · {{ wp.produk?.nama_produk }} · {{ wp.tanggal }}</option>
</select>
</div>
<div>
<InputLabel value="Work Center" />
<select v-model="form.work_center_id" class="mt-1.5 block w-full rounded-xl border-gray-300 text-xs dark:bg-gray-900">
<option value="">— auto dari WP —</option>
<option v-for="wc in workCenters" :key="wc.id" :value="wc.id">{{ wc.code }} - {{ wc.name }}</option>
</select>
</div>
<div>
<InputLabel value="Required Date (MO/run)" />
<TextInput v-model="form.required_date" type="date" class="mt-1.5 block w-full" required />
<p class="text-[11px] text-gray-500">H-1: kirim paling lambat sehari sebelum tanggal ini</p>
</div>
<div>
<InputLabel value="Catatan" />
<TextInput v-model="form.notes" class="mt-1.5 block w-full" placeholder="opsional" />
</div>
</div>

<div class="mt-4">
<div class="flex items-center justify-between">
<InputLabel value="Items (BOM explosion)" />
<button type="button" @click="addItem" class="rounded-lg bg-gray-100 px-2 py-1 text-xs font-bold hover:bg-gray-200">+ Item</button>
</div>
<div v-for="(it,idx) in form.items" :key="idx" class="mt-2 grid grid-cols-12 gap-2">
<div class="col-span-5">
<select v-model="it.produk_id" class="w-full rounded-xl border-gray-300 text-xs dark:bg-gray-900" required>
<option value="" disabled>Pilih material</option>
<option v-for="p in produks" :key="p.id" :value="p.id">{{ p.kode_produk }} - {{ p.nama_produk }}</option>
</select>
</div>
<div class="col-span-3"><TextInput v-model="it.required_qty" type="number" step="0.0001" placeholder="Qty" class="w-full" required /></div>
<div class="col-span-3">
<select v-model="it.uom_id" class="w-full rounded-xl border-gray-300 text-xs dark:bg-gray-900">
<option value="">UOM auto</option>
<option v-for="u in uoms" :key="u.id" :value="u.id">{{ u.code }}</option>
</select>
</div>
<div class="col-span-1"><button type="button" @click="removeItem(idx)" class="text-rose-600">✕</button></div>
</div>
</div>

<div class="mt-4 flex justify-end gap-2">
<SecondaryButton @click="router.visit(route('scm.supply.index'))">Batal</SecondaryButton>
<PrimaryButton :disabled="form.processing" @click="submit">Simpan Supply</PrimaryButton>
</div>
</div>
</div>
</AuthenticatedLayout>
</template>
