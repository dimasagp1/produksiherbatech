<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

interface Plan {
    id: number;
    batch_number: string;
    produk?: { nama_produk: string };
}
interface Material {
    id: number;
    nama_produk: string;
}
interface Uom {
    id: number;
    code: string;
}

const props = defineProps<{
    plans: Plan[];
    materials: Material[];
    uoms: Uom[];
    initialBatch?: string;
}>();

const emit = defineEmits<{
    (e: 'submit', data: Record<string, unknown>): void;
    (e: 'close'): void;
}>();

const form = useForm({
    weekly_plan_id: '',
    batch_number: props.initialBatch ?? '',
    produk_id: '',
    material_name: '',
    quantity: '',
    uom_id: '',
    defect_reason: '',
});

watch(
    () => props.initialBatch,
    (v) => {
        if (v) form.batch_number = v;
    },
);

function onPlanChange() {
    const plan = props.plans.find(
        (p) => String(p.id) === String(form.weekly_plan_id),
    );
    if (plan) form.batch_number = plan.batch_number;
}

function submit() {
    emit('submit', {
        weekly_plan_id: form.weekly_plan_id
            ? Number(form.weekly_plan_id)
            : null,
        batch_number: form.batch_number,
        produk_id: form.produk_id ? Number(form.produk_id) : null,
        material_name: form.material_name,
        quantity: Number(form.quantity),
        uom_id: form.uom_id ? Number(form.uom_id) : null,
        defect_reason: form.defect_reason || null,
    });
}
</script>

<template>
    <form class="space-y-4 p-6" @submit.prevent="submit">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Scrap Material — Manual
        </h3>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <InputLabel value="Weekly Plan (opsional)" />
                <select
                    v-model="form.weekly_plan_id"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                    @change="onPlanChange"
                >
                    <option value="">— tanpa plan —</option>
                    <option v-for="p in plans" :key="p.id" :value="p.id">
                        {{ p.produk?.nama_produk ?? '-' }} ·
                        {{ p.batch_number }}
                    </option>
                </select>
            </div>
            <div>
                <InputLabel value="Batch Number" />
                <TextInput
                    v-model="form.batch_number"
                    class="mt-1 block w-full"
                    required
                />
                <InputError :message="form.errors.batch_number" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Produk terkait (opsional)" />
                <select
                    v-model="form.produk_id"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                >
                    <option value="">—</option>
                    <option v-for="m in materials" :key="m.id" :value="m.id">
                        {{ m.nama_produk }}
                    </option>
                </select>
            </div>
            <div>
                <InputLabel value="Nama Material" />
                <TextInput
                    v-model="form.material_name"
                    class="mt-1 block w-full"
                    required
                />
                <InputError :message="form.errors.material_name" class="mt-1" />
            </div>
            <div>
                <InputLabel value="Quantity" />
                <TextInput
                    v-model="form.quantity"
                    type="number"
                    step="0.001"
                    min="0.001"
                    class="mt-1 block w-full"
                    required
                />
                <InputError :message="form.errors.quantity" class="mt-1" />
            </div>
            <div>
                <InputLabel value="UOM" />
                <select
                    v-model="form.uom_id"
                    class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                >
                    <option value="">—</option>
                    <option v-for="u in uoms" :key="u.id" :value="u.id">
                        {{ u.code }}
                    </option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <InputLabel value="Alasan / defect" />
                <TextInput
                    v-model="form.defect_reason"
                    class="mt-1 block w-full"
                    placeholder="Mis. label rusak, botol pecah"
                />
            </div>
        </div>
        <div class="flex justify-end gap-2">
            <SecondaryButton type="button" @click="emit('close')"
                >Batal</SecondaryButton
            >
            <PrimaryButton :disabled="form.processing">Simpan</PrimaryButton>
        </div>
    </form>
</template>
