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
    kode_produk?: string;
    nama_produk: string;
    item_type?: string;
    odoo_uom?: string;
    uom?: { id: number; code: string; name: string };
}

interface BomItem {
    id: number;
    material_produk_id?: number | null;
    material_name: string;
    quantity: number;
    uom_id?: number | null;
    uom?: { code: string; name: string };
    material_produk?: Produk | null;
}

interface Bom {
    id: number;
    produk_id: number;
    version?: string;
    items: BomItem[];
    produk?: Produk;
}

interface Plan {
    id: number;
    produk_id: number;
    batch_number: string;
    target_output?: number;
    proses?: string;
    tanggal: string;
    status: string;
    line?: { nama_line: string };
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
        uom_code?: string;
    }>,
});

const selectedPlan = ref<Plan | null>(null);
const activeBom = computed(() =>
    selectedPlan.value?.produk_id
        ? props.boms[String(selectedPlan.value.produk_id)]
        : null,
);

function stockFor(pid: string): number {
    if (!pid) return 0;
    return (props.stocks[pid] ?? []).reduce(
        (s, r) => s + Number(r.quantity || 0),
        0,
    );
}

function getMaterialUom(pid: string): string {
    if (!pid) return '';
    const mat = props.materials.find((m) => String(m.id) === String(pid));
    return mat?.uom?.code || mat?.odoo_uom || '';
}

watch(
    () => form.weekly_plan_id,
    (id) => {
        selectedPlan.value =
            props.plans.find((p) => String(p.id) === String(id)) ?? null;
        const bom = activeBom.value;
        const plan = selectedPlan.value;

        if (bom && bom.items?.length > 0) {
            form.items = bom.items.map((it) => {
                // If BOM item quantity is per unit finished good, multiply by target_output if target_output > 0
                // Note: Standard quantity
                const stdQty = (Number(it.quantity) || 0);

                return {
                    produk_id: it.material_produk_id ? String(it.material_produk_id) : '',
                    material_name: it.material_name,
                    quantity_used: '',
                    quantity_standard: String(stdQty),
                    uom_id: it.uom_id ? String(it.uom_id) : (it.material_produk?.uom?.id ? String(it.material_produk.uom.id) : ''),
                    uom_code: it.uom?.code || it.material_produk?.uom?.code || it.material_produk?.odoo_uom || '',
                };
            });
        } else {
            form.items = [
                {
                    produk_id: '',
                    material_name: '',
                    quantity_used: '',
                    quantity_standard: '',
                    uom_id: '',
                    uom_code: '',
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
        uom_code: '',
    });
}

function removeItem(idx: number) {
    if (form.items.length > 1) {
        form.items.splice(idx, 1);
    }
}

function fillAllWithStandard() {
    form.items.forEach((item) => {
        if (item.quantity_standard) {
            item.quantity_used = item.quantity_standard;
        }
    });
}

function onMaterialChange(idx: number) {
    const pid = form.items[idx].produk_id;
    if (!pid) return;
    const mat = props.materials.find((m) => String(m.id) === pid);
    if (mat) {
        form.items[idx].material_name = mat.nama_produk;
        form.items[idx].uom_id = mat.uom?.id ? String(mat.uom.id) : '';
        form.items[idx].uom_code = mat.uom?.code || mat.odoo_uom || '';
    }
}

function varianceFor(idx: number): number {
    const used = Number(form.items[idx].quantity_used) || 0;
    const std = Number(form.items[idx].quantity_standard) || 0;
    return Number((used - std).toFixed(4));
}

function ratioFor(idx: number): number | null {
    const used = Number(form.items[idx].quantity_used) || 0;
    const std = Number(form.items[idx].quantity_standard) || 0;
    return std > 0 ? Number((((used - std) / std) * 100).toFixed(2)) : null;
}

// Summary computations
const totalStandard = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.quantity_standard) || 0), 0),
);
const totalActual = computed(() =>
    form.items.reduce((sum, item) => sum + (Number(item.quantity_used) || 0), 0),
);
const totalVariance = computed(() => Number((totalActual.value - totalStandard.value).toFixed(4)));
const overallRatio = computed(() =>
    totalStandard.value > 0
        ? Number((((totalActual.value - totalStandard.value) / totalStandard.value) * 100).toFixed(2))
        : null,
);

function submit() {
    form.post(route('scm.material-usage.store'));
}
</script>

<template>
    <Head title="Input Material Usage (Pemakaian Bahan)" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                        Input Material Usage
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Pencatatan aktual pemakaian bahan baku & kemas terhadap standar BOM · Target deviasi ≤ {{ ratioTarget }}%
                    </p>
                </div>
                <Link
                    :href="route('scm.material-usage.index')"
                    class="rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-xs transition hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                >
                    Kembali
                </Link>
            </div>
        </template>

        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form class="space-y-6" @submit.prevent="submit">
                <!-- CARD 1: Production Order & Header -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        1. Informasi Produk Jadi & Perintah Produksi
                    </h3>
                    
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                        <div class="sm:col-span-2">
                            <InputLabel value="Pilih Weekly Plan / Batch Produksi" />
                            <select
                                v-model="form.weekly_plan_id"
                                class="mt-1.5 w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                required
                            >
                                <option value="" disabled>— Pilih Perintah Produksi / Batch —</option>
                                <option
                                    v-for="p in plans"
                                    :key="p.id"
                                    :value="p.id"
                                >
                                    {{ p.batch_number }} · {{ p.produk?.nama_produk ?? 'Produk' }} ({{ p.tanggal }} · {{ p.proses ?? 'mixing' }})
                                </option>
                            </select>
                            <InputError :message="form.errors.weekly_plan_id" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="Tanggal Pemakaian" />
                            <TextInput
                                v-model="form.usage_date"
                                type="date"
                                class="mt-1.5 block w-full text-xs"
                                required
                            />
                            <InputError :message="form.errors.usage_date" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel value="Shift Kerja" />
                            <select
                                v-model="form.shift"
                                class="mt-1.5 w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                            >
                                <option value="shift1">Shift 1 (Pagi)</option>
                                <option value="shift2">Shift 2 (Siang)</option>
                            </select>
                        </div>
                    </div>

                    <!-- FINISHED GOOD HIGHLIGHT CARD -->
                    <div
                        v-if="selectedPlan"
                        class="mt-4 rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/30"
                    >
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div class="space-y-1">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">
                                    Produk Jadi Yang Diproduksi
                                </span>
                                <div class="text-base font-extrabold text-slate-900 dark:text-slate-100">
                                    {{ selectedPlan.produk?.nama_produk }}
                                </div>
                                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-600 dark:text-slate-300">
                                    <span v-if="selectedPlan.produk?.kode_produk" class="font-mono font-bold bg-white px-2 py-0.5 rounded border border-indigo-200 dark:bg-slate-900 dark:border-indigo-800">
                                        {{ selectedPlan.produk.kode_produk }}
                                    </span>
                                    <span class="font-mono bg-white px-2 py-0.5 rounded border border-indigo-200 dark:bg-slate-900 dark:border-indigo-800">
                                        Batch: {{ selectedPlan.batch_number }}
                                    </span>
                                    <span v-if="selectedPlan.line">
                                        Line: {{ selectedPlan.line.nama_line }}
                                    </span>
                                    <span v-if="selectedPlan.proses">
                                        · Proses: {{ selectedPlan.proses }}
                                    </span>
                                </div>
                            </div>
                            <div class="text-left sm:text-right border-t sm:border-t-0 pt-2 sm:pt-0 border-indigo-200/50">
                                <span class="text-[10px] font-semibold text-slate-500 dark:text-slate-400">Target Output Plan</span>
                                <div class="text-lg font-black text-indigo-700 dark:text-indigo-300">
                                    {{ selectedPlan.target_output ? selectedPlan.target_output.toLocaleString() : '-' }} <span class="text-xs font-normal">Unit</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- BOM Status Alert -->
                    <div
                        v-if="activeBom"
                        class="mt-3 flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-2.5 text-xs text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/40 dark:text-emerald-200"
                    >
                        <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>
                            BOM formula aktif ditemukan (Versi: {{ activeBom.version || 'v1' }}) — <strong>{{ activeBom.items.length }} material standar</strong> terisi otomatis.
                        </span>
                    </div>
                    <div
                        v-else-if="form.weekly_plan_id"
                        class="mt-3 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2.5 text-xs text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>
                            Belum ada BOM aktif untuk produk ini. Anda dapat memilih bahan dan mengisi kuantitas standar manual di bawah.
                        </span>
                    </div>

                    <!-- Notes -->
                    <div class="mt-4">
                        <InputLabel value="Catatan Tambahan (Opsional)" />
                        <TextInput
                            v-model="form.notes"
                            type="text"
                            class="mt-1 block w-full text-xs"
                            placeholder="Catatan kendala, perubahan lot, atau alasan deviasi pemakaian..."
                        />
                    </div>
                </div>

                <!-- CARD 2: Material Items Table -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-xs dark:border-slate-700 dark:bg-slate-800">
                    <div class="mb-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                2. Rincian Komponen Bahan (Bahan Baku & Bahan Kemas)
                            </h3>
                            <p class="text-[11px] text-slate-400">
                                Masukkan kuantitas aktual bahan yang terpakai di lini produksi.
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="activeBom"
                                type="button"
                                class="rounded-xl border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                @click="fillAllWithStandard"
                            >
                                Isi Aktual = Standar
                            </button>
                            <SecondaryButton
                                type="button"
                                class="py-1.5 text-xs rounded-xl"
                                @click="addItem"
                            >
                                + Tambah Bahan
                            </SecondaryButton>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-xs min-w-[700px]">
                            <thead class="border-b border-slate-200 bg-slate-50/80 text-slate-600 dark:border-slate-700 dark:bg-slate-900/60 dark:text-slate-300">
                                <tr>
                                    <th class="px-3 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Nama Material / Bahan</th>
                                    <th class="px-3 py-3 text-left font-bold uppercase tracking-wider text-[11px]">Stok Tersedia</th>
                                    <th class="px-3 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Standar (Plan)</th>
                                    <th class="px-3 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Aktual (Terpakai)</th>
                                    <th class="px-3 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Selisih</th>
                                    <th class="px-3 py-3 text-right font-bold uppercase tracking-wider text-[11px]">Rasio Deviasi</th>
                                    <th class="w-8 px-2 py-3 text-center"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <tr
                                    v-for="(item, idx) in form.items"
                                    :key="idx"
                                    class="hover:bg-slate-50/60 dark:hover:bg-slate-700/30"
                                >
                                    <!-- Material Selection -->
                                    <td class="px-3 py-2.5">
                                        <div class="space-y-1">
                                            <select
                                                v-model="item.produk_id"
                                                class="w-full rounded-lg border-slate-300 text-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
                                                @change="onMaterialChange(idx)"
                                            >
                                                <option value="">— Pilih dari Master Bahan / Input Bebas —</option>
                                                <option
                                                    v-for="m in materials"
                                                    :key="m.id"
                                                    :value="String(m.id)"
                                                >
                                                    [{{ m.item_type === 'rm' ? 'RM' : 'PM' }}] {{ m.nama_produk }} {{ m.kode_produk ? `(${m.kode_produk})` : '' }}
                                                </option>
                                            </select>
                                            <TextInput
                                                v-if="!item.produk_id"
                                                v-model="item.material_name"
                                                class="block w-full text-xs"
                                                placeholder="Nama bahan / manual..."
                                                required
                                            />
                                        </div>
                                    </td>

                                    <!-- Warehouse Stock -->
                                    <td class="px-3 py-2.5">
                                        <div v-if="item.produk_id" class="font-mono text-[11px] text-slate-600 dark:text-slate-300">
                                            <span class="font-semibold">{{ stockFor(item.produk_id) }}</span>
                                            <span class="text-slate-400 ml-1">{{ getMaterialUom(item.produk_id) }}</span>
                                        </div>
                                        <div v-else class="text-slate-400 font-mono text-[11px]">-</div>
                                    </td>

                                    <!-- Standard Qty -->
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <TextInput
                                                v-model="item.quantity_standard"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                class="w-24 text-right text-xs font-mono"
                                                placeholder="0.00"
                                            />
                                            <span class="text-[10px] text-slate-400 w-8 truncate">{{ item.uom_code || getMaterialUom(item.produk_id) }}</span>
                                        </div>
                                    </td>

                                    <!-- Actual Qty Used -->
                                    <td class="px-3 py-2.5">
                                        <div class="flex items-center justify-end gap-1">
                                            <TextInput
                                                v-model="item.quantity_used"
                                                type="number"
                                                step="0.0001"
                                                min="0"
                                                class="w-24 text-right text-xs font-mono font-bold"
                                                placeholder="0.00"
                                                required
                                            />
                                            <span class="text-[10px] text-slate-400 w-8 truncate">{{ item.uom_code || getMaterialUom(item.produk_id) }}</span>
                                        </div>
                                    </td>

                                    <!-- Variance -->
                                    <td class="px-3 py-2.5 text-right font-mono font-semibold">
                                        <span
                                            v-if="item.quantity_used"
                                            :class="varianceFor(idx) > 0 ? 'text-rose-600' : (varianceFor(idx) < 0 ? 'text-emerald-600' : 'text-slate-600')"
                                        >
                                            {{ varianceFor(idx) > 0 ? '+' : '' }}{{ varianceFor(idx) }}
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>

                                    <!-- Deviasi % -->
                                    <td class="px-3 py-2.5 text-right font-bold">
                                        <span
                                            v-if="ratioFor(idx) !== null"
                                            :class="ratioFor(idx)! > ratioTarget ? 'text-rose-600' : 'text-emerald-600'"
                                        >
                                            {{ ratioFor(idx)! > 0 ? '+' : '' }}{{ ratioFor(idx) }}%
                                        </span>
                                        <span v-else class="text-slate-400">-</span>
                                    </td>

                                    <!-- Delete Row -->
                                    <td class="px-2 py-2.5 text-center">
                                        <button
                                            type="button"
                                            class="rounded p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950"
                                            title="Hapus baris"
                                            @click="removeItem(idx)"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>

                            <!-- Summary Footer -->
                            <tfoot class="border-t-2 border-slate-200 bg-slate-50/90 font-bold dark:border-slate-700 dark:bg-slate-900/80">
                                <tr>
                                    <td colspan="2" class="px-3 py-3 text-slate-700 dark:text-slate-300">
                                        Total Kuantitas Konsumsi
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-slate-800 dark:text-slate-200">
                                        {{ totalStandard }}
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono text-indigo-700 dark:text-indigo-300">
                                        {{ totalActual }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right font-mono"
                                        :class="totalVariance > 0 ? 'text-rose-600' : 'text-emerald-600'"
                                    >
                                        {{ totalVariance > 0 ? '+' : '' }}{{ totalVariance }}
                                    </td>
                                    <td
                                        class="px-3 py-3 text-right"
                                        :class="overallRatio !== null && overallRatio > ratioTarget ? 'text-rose-600' : 'text-emerald-600'"
                                    >
                                        {{ overallRatio !== null ? `${overallRatio > 0 ? '+' : ''}${overallRatio}%` : '-' }}
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <InputError :message="form.errors.items as string" class="mt-2" />
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-end gap-3">
                    <Link
                        :href="route('scm.material-usage.index')"
                        class="rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-xs font-bold text-slate-700 shadow-xs hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
                    >
                        Batal
                    </Link>
                    <PrimaryButton
                        :disabled="form.processing"
                        class="rounded-xl px-6 py-2.5 text-xs font-bold"
                    >
                        {{ form.processing ? 'Menyimpan...' : 'Simpan Material Usage' }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>

