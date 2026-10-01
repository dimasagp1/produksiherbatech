<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

interface Produk {
    id: number;
    nama_produk: string;
    item_type?: string;
}
interface BomItem {
    material_produk_id?: number | null;
    material_name: string;
    quantity: number;
    uom_id?: number | null;
}
interface Bom {
    produk_id: number;
    items: BomItem[];
}
interface Plan {
    id: number;
    produk_id: number;
    batch_number: string;
    tanggal: string;
    status: string;
    produk?: Produk;
}

const props = defineProps<{
    plans: Plan[];
    boms: Record<string, Bom>;
    materials: Produk[];
    stocks: Record<
        string,
        Array<{ quantity: number; batch_number?: string | null }>
    >;
    ratioTarget: number;
}>();

const form = useForm({
    weekly_plan_id: '',
    usage_date: new Date().toISOString().slice(0, 10),
    shift: 'shift1',
    notes: '',
    items: [] as Array<{
        produk_id: string;
        material_name: string;
        quantity_used: string;
        quantity_standard: string;
        uom_id: string;
    }>,
});

const selectedPlan = ref<Plan | null>(null);
const activeBom = computed(() =>
    form.weekly_plan_id
        ? props.boms[String(selectedPlan.value?.produk_id ?? '')]
        : null,
);

function stockFor(pid: string): number {
    return (props.stocks[pid] ?? []).reduce(
        (s, r) => s + Number(r.quantity || 0),
        0,
    );
}

watch(
    () => form.weekly_plan_id,
    (id) => {
        selectedPlan.value =
            props.plans.find((p) => String(p.id) === String(id)) ?? null;
        const bom = activeBom.value;
        form.items = (bom?.items ?? []).map((it) => ({
            produk_id: it.material_produk_id
                ? String(it.material_produk_id)
                : '',
            material_name: it.material_name,
            quantity_used: '',
            quantity_standard: String(it.quantity),
            uom_id: it.uom_id ? String(it.uom_id) : '',
        }));
        if (form.items.length === 0) {
            form.items = [
                {
                    produk_id: '',
                    material_name: '',
                    quantity_used: '',
                    quantity_standard: '',
                    uom_id: '',
                },
            ];
        }
    },
);

function addItem() {
    form.items.push({
        produk_id: '',
        material_name: '',
        quantity_used: '',
        quantity_standard: '',
        uom_id: '',
    });
}

function onMaterialChange(idx: number) {
    const pid = form.items[idx].produk_id;
    if (!pid) return;
    const mat = props.materials.find((m) => String(m.id) === pid);
    if (mat) form.items[idx].material_name = mat.nama_produk;
}

function ratioFor(idx: number): number | null {
    const used = Number(form.items[idx].quantity_used) || 0;
    const std = Number(form.items[idx].quantity_standard) || 0;
    return std > 0 ? Number((((used - std) / std) * 100).toFixed(2)) : null;
}

function submit() {
    form.post(route('scm.material-usage.store'));
}
</script>

<template>
    <Head title="Input Material Usage" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold text-gray-800 dark:text-gray-200"
                    >
                        Input Material Usage
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        Ratio = (Actual − Standard) / Standard × 100% · Target ≤
                        {{ ratioTarget }}%
                    </p>
                </div>
                <Link
                    :href="route('scm.material-usage.index')"
                    class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-300"
                    >Kembali</Link
                >
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="submit">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <h3
                        class="mb-4 text-sm font-semibold uppercase text-gray-500"
                    >
                        Info Penggunaan
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel value="Weekly Plan / Batch" />
                            <select
                                v-model="form.weekly_plan_id"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                required
                            >
                                <option value="" disabled>Pilih plan</option>
                                <option
                                    v-for="p in plans"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.tanggal }} ·
                                    {{ p.produk?.nama_produk ?? '-' }} ·
                                    {{ p.batch_number }} ({{ p.status }})
                                </option>
                            </select>
                            <InputError
                                :message="form.errors.weekly_plan_id"
                                class="mt-1"
                            />
                        </div>
                        <div>
                            <InputLabel value="Tanggal" />
                            <TextInput
                                v-model="form.usage_date"
                                type="date"
                                class="mt-1 block w-full"
                                required
                            />
                        </div>
                        <div>
                            <InputLabel value="Shift" />
                            <select
                                v-model="form.shift"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                            >
                                <option value="shift1">Shift 1</option>
                                <option value="shift2">Shift 2</option>
                            </select>
                        </div>
                    </div>
                    <div
                        v-if="activeBom"
                        class="mt-3 rounded-lg bg-indigo-50 px-3 py-2 text-xs text-indigo-800 dark:bg-indigo-950/40 dark:text-indigo-200"
                    >
                        BOM aktif ditemukan —
                        {{ activeBom.items.length }} material standard terisi
                        otomatis.
                    </div>
                    <div
                        v-else-if="form.weekly_plan_id"
                        class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        Tidak ada BOM aktif untuk produk ini — isi standard
                        manual.
                    </div>
                </div>

                <div
                    class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="mb-3 flex items-center justify-between">
                        <h3
                            class="text-sm font-semibold uppercase text-gray-500"
                        >
                            Material Items
                        </h3>
                        <SecondaryButton
                            type="button"
                            class="py-1 text-xs"
                            @click="addItem"
                            >+ Item</SecondaryButton
                        >
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/60">
                                <tr>
                                    <th class="px-2 py-2 text-left">
                                        Material
                                    </th>
                                    <th class="px-2 py-2 text-left">Stok</th>
                                    <th class="px-2 py-2 text-right">
                                        Standard
                                    </th>
                                    <th class="px-2 py-2 text-right">Actual</th>
                                    <th class="px-2 py-2 text-right">
                                        Ratio %
                                    </th>
                                </tr>
                            </thead>
                            <tbody
                                class="divide-y divide-gray-100 dark:divide-gray-700"
                            >
                                <tr
                                    v-for="(item, idx) in form.items"
                                    :key="idx"
                                >
                                    <td class="px-2 py-1.5">
                                        <select
                                            v-model="item.produk_id"
                                            class="w-full rounded border-gray-300 text-xs dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                                            @change="onMaterialChange(idx)"
                                        >
                                            <option value="">
                                                — pilih / manual —
                                            </option>
                                            <option
                                                v-for="m in materials"
                                                :key="m.id"
                                                :value="m.id"
                                            >
                                                {{ m.nama_produk }}
                                            </option>
                                        </select>
                                        <TextInput
                                            v-if="!item.produk_id"
                                            v-model="item.material_name"
                                            class="mt-1 block w-full"
                                            placeholder="Nama material manual"
                                        />
                                    </td>
                                    <td class="px-2 py-1.5 text-gray-500">
                                        {{
                                            item.produk_id
                                                ? stockFor(item.produk_id)
                                                : '-'
                                        }}
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <TextInput
                                            v-model="item.quantity_standard"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            class="block w-24 text-right"
                                        />
                                    </td>
                                    <td class="px-2 py-1.5">
                                        <TextInput
                                            v-model="item.quantity_used"
                                            type="number"
                                            step="0.001"
                                            min="0"
                                            class="block w-24 text-right"
                                            required
                                        />
                                    </td>
                                    <td
                                        class="px-2 py-1.5 text-right font-bold"
                                        :class="
                                            ratioFor(idx) !== null &&
                                            ratioFor(idx)! > ratioTarget
                                                ? 'text-red-600'
                                                : 'text-emerald-600'
                                        "
                                    >
                                        {{ ratioFor(idx) ?? '-' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <InputError
                        :message="form.errors.items as string"
                        class="mt-2"
                    />
                </div>

                <PrimaryButton
                    :disabled="form.processing"
                    class="w-full justify-center"
                    >Simpan Material Usage</PrimaryButton
                >
            </form>
        </div>
    </AuthenticatedLayout>
</template>
