<script setup lang="ts">
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

interface Produk {
    id: number;
    kode_produk: string;
    nama_produk: string;
    proses_default: string;
}
interface Mesin {
    id: number;
    nama_mesin: string;
    ct: number;
}
interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
}
interface AlasanDowntime {
    id: number;
    nama_alasan: string;
    tipe_input: string;
    durasi_default_menit: number | null;
}
interface WeeklyPlan {
    id: number;
    produk_id: number;
    tanggal: string;
    proses: string;
    batch_number: string;
    status: string;
    odoo_mo_id?: number | null;
    mo_status?: string;
    target_output?: number;
    mp_count?: number;
    multiplier?: number;
    line_id?: number | null;
    produk?: Produk;
    line?: Line | null;
}

const props = defineProps<{
    produks: Produk[];
    mesins: Mesin[];
    lines: Line[];
    alasanDowntimes: AlasanDowntime[];
    weeklyPlans: WeeklyPlan[];
    preselect?: {
        produk_id?: string;
        weekly_plan_id?: string;
        proses?: string;
    };
}>();

const form = useForm({
    produk_id: '',
    weekly_plan_id: '',
    proses: '',
    batch_number: '',
    mesin_id: '',
    line_id: '',
    tanggal: (() => {
        const d = new Date();
        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    })(),
    shift: 'shift1',
    target_mp: '',
    total_mp: '1',
    capacity_fisik: '',
});

const selectedProduk = ref<Produk | null>(null);
const selectedMesin = ref<Mesin | null>(null);
const selectedWeeklyPlan = ref<WeeklyPlan | null>(null);
const selectedWeeklyPlanId = ref('');

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
};

// Filter weekly plans by tanggal using props (client-side, no API call needed)
const availablePlans = computed(() => {
    if (!form.tanggal) return [];
    return props.weeklyPlans.filter(
        (wp) =>
            wp.tanggal === form.tanggal &&
            (wp.status === 'aktif' || wp.status === 'draft') &&
            wp.mo_status !== 'cancelled',
    );
});

// Available products: only those with weekly plan on selected tanggal
const availableProduks = computed(() => {
    const ids = new Set(availablePlans.value.map((wp) => wp.produk_id));
    return props.produks.filter((p) => ids.has(p.id));
});

// Available plans for selected product (can be multiple batches/processes)
const availablePlansForProduk = computed(() => {
    if (!form.produk_id) return [];
    return availablePlans.value.filter(
        (wp) => wp.produk_id === Number(form.produk_id),
    );
});

function applyWeeklyPlan(wp: WeeklyPlan | null) {
    selectedWeeklyPlan.value = wp;
    if (!wp) {
        form.weekly_plan_id = '';
        form.proses = '';
        form.batch_number = '';
        return;
    }

    form.weekly_plan_id = String(wp.id);
    form.proses = wp.proses;
    form.batch_number = wp.batch_number;

    if (wp.line_id) {
        form.line_id = String(wp.line_id);
    }

    // Auto-fill Target & Capacity based on Odoo / Weekly Plan
    const targetOutput = Number(wp.target_output) || 0;
    const mpCount = Number(wp.mp_count) || 0;
    const multiplierVal = Number(wp.multiplier) || 2000;

    if (targetOutput > 0) {
        if (mpCount > 0) {
            form.total_mp = String(mpCount);
            form.target_mp = String(
                wp.multiplier ? wp.multiplier : Math.round(targetOutput / mpCount),
            );
            form.capacity_fisik = String(targetOutput);
        } else {
            // Fresh from Odoo MO (MP count not set yet)
            form.total_mp = '1';
            form.target_mp = String(targetOutput);
            form.capacity_fisik = String(targetOutput);
        }
    } else if (mpCount > 0) {
        form.total_mp = String(mpCount);
        form.target_mp = String(multiplierVal);
        form.capacity_fisik = String(mpCount * multiplierVal);
    } else {
        form.total_mp = '1';
        form.target_mp = String(multiplierVal);
        form.capacity_fisik = String(multiplierVal);
    }
}

// When tanggal changes → reset product selection
watch(
    () => form.tanggal,
    () => {
        form.produk_id = '';
        selectedWeeklyPlanId.value = '';
        selectedProduk.value = null;
        applyWeeklyPlan(null);
    },
);

// When produk selected → handle single vs multiple plans
watch(
    () => form.produk_id,
    (id) => {
        if (!id) {
            selectedProduk.value = null;
            selectedWeeklyPlanId.value = '';
            applyWeeklyPlan(null);
            return;
        }
        selectedProduk.value =
            props.produks.find((x) => x.id === Number(id)) ?? null;
        const plans = availablePlansForProduk.value;

        if (plans.length === 1) {
            selectedWeeklyPlanId.value = String(plans[0].id);
            applyWeeklyPlan(plans[0]);
        } else {
            selectedWeeklyPlanId.value = '';
            applyWeeklyPlan(null);
        }
    },
);

// When user selects a specific plan ID from dropdown
watch(selectedWeeklyPlanId, (id) => {
    if (!id) {
        if (availablePlansForProduk.value.length > 1) {
            applyWeeklyPlan(null);
        }
        return;
    }
    const wp = props.weeklyPlans.find((w) => w.id === Number(id)) ?? null;
    applyWeeklyPlan(wp);
});

// Auto-calculate Capacity Fisik = Target MP × Total MP
const capacityFisikAuto = computed(() => {
    const target = parseFloat(form.target_mp) || 0;
    const total = parseInt(form.total_mp) || 0;
    return target * total;
});

// Sync form.capacity_fisik with auto-calculated value when target_mp or total_mp changes
watch(
    [() => form.target_mp, () => form.total_mp],
    () => {
        form.capacity_fisik = String(capacityFisikAuto.value);
    },
);

// Auto-lookup mesin CT
watch(
    () => form.mesin_id,
    (id) => {
        selectedMesin.value =
            props.mesins.find((x) => x.id === Number(id)) ?? null;
    },
);

const ct = computed(() => selectedMesin.value?.ct ?? 0);

// Handle preselect from query params (parallel flow redirect)
onMounted(() => {
    if (props.preselect?.weekly_plan_id) {
        const wp = props.weeklyPlans.find(
            (w) => w.id === Number(props.preselect!.weekly_plan_id),
        );
        if (wp) {
            form.tanggal = wp.tanggal;
            form.produk_id = String(wp.produk_id);
            selectedProduk.value =
                props.produks.find((p) => p.id === Number(wp.produk_id)) ??
                null;
            selectedWeeklyPlanId.value = String(wp.id);
            applyWeeklyPlan(wp);
        }
    } else if (props.preselect?.produk_id) {
        const wp = props.weeklyPlans.find(
            (w) =>
                w.produk_id === Number(props.preselect!.produk_id) &&
                (!props.preselect?.proses ||
                    w.proses === props.preselect!.proses),
        );
        if (wp) {
            form.tanggal = wp.tanggal;
            form.produk_id = String(wp.produk_id);
            selectedProduk.value =
                props.produks.find((p) => p.id === Number(wp.produk_id)) ??
                null;
            selectedWeeklyPlanId.value = String(wp.id);
            applyWeeklyPlan(wp);
        }
    }
});

function submit() {
    if (!form.weekly_plan_id) {
        alert('Pilih tanggal dan produk yang sesuai dengan Weekly Plan.');
        return;
    }
    form.post(route('leader.laporan-harian.store'));
}
</script>

<template>
    <Head title="Input Laporan Harian" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Input Laporan Harian
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Mulai pencatatan laporan produksi baru dari Weekly Plan
                    </p>
                </div>
                <Link
                    :href="route('leader.laporan-harian.index')"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                    >Kembali</Link
                >
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-3 sm:px-6 lg:px-8">
            <!-- Alert duplikasi -->
            <div
                v-if="form.errors.weekly_plan_id || form.errors.produk_id"
                class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20"
            >
                <p class="text-sm font-medium text-red-800 dark:text-red-200">
                    {{ form.errors.weekly_plan_id || form.errors.produk_id }}
                </p>
                <p class="mt-1 text-xs text-red-700 dark:text-red-300">
                    Silakan cek laporan yang sudah ada atau pilih produk/proses
                    lain.
                </p>
                <Link
                    :href="route('leader.laporan-harian.index')"
                    class="mt-2 inline-block text-xs font-medium text-red-700 underline dark:text-red-300"
                    >Lihat Laporan Harian →</Link
                >
            </div>

            <form @submit.prevent="submit">
                <div class="space-y-6">
                    <!-- Informasi Produk -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                        >
                            Informasi Produk
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <InputLabel value="Tanggal" />
                                <TextInput
                                    v-model="form.tanggal"
                                    type="date"
                                    class="mt-1 block w-full"
                                    required
                                />
                                <InputError
                                    :message="form.errors.tanggal"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="Shift" />
                                <select
                                    v-model="form.shift"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    required
                                >
                                    <option value="shift1">Shift 1</option>
                                    <option value="shift2">Shift 2</option>
                                </select>
                                <InputError
                                    :message="form.errors.shift"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="Produk" />
                                <select
                                    v-model="form.produk_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    required
                                    :disabled="availableProduks.length === 0"
                                >
                                    <option value="" disabled>
                                        {{
                                            availableProduks.length === 0
                                                ? 'Tidak ada produk untuk tanggal ini'
                                                : 'Pilih Produk'
                                        }}
                                    </option>
                                    <option
                                        v-for="p in availableProduks"
                                        :key="p.id"
                                        :value="p.id"
                                    >
                                        {{ p.nama_produk }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        form.errors.produk_id ||
                                        form.errors.weekly_plan_id
                                    "
                                    class="mt-1"
                                />
                            </div>
                            <!-- Batch / Proses selector (when product has multiple batches/processes on date) -->
                            <div
                                v-if="
                                    form.produk_id &&
                                    availablePlansForProduk.length > 1
                                "
                            >
                                <InputLabel value="Batch / Proses (Odoo Plan)" />
                                <select
                                    v-model="selectedWeeklyPlanId"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    required
                                >
                                    <option value="" disabled>
                                        Pilih Batch / Proses
                                    </option>
                                    <option
                                        v-for="wp in availablePlansForProduk"
                                        :key="wp.id"
                                        :value="String(wp.id)"
                                    >
                                        {{ prosesLabel[wp.proses] ?? wp.proses }} — Batch: {{ wp.batch_number }} {{ wp.target_output ? `(Target: ${Number(wp.target_output).toLocaleString('id-ID')} pcs)` : '' }}
                                    </option>
                                </select>
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Produk ini memiliki
                                    {{ availablePlansForProduk.length }} batch/rencana kerja pada tanggal ini.
                                </p>
                            </div>
                            <!-- Proses (auto when single plan) -->
                            <div v-else-if="form.proses">
                                <InputLabel value="Proses" />
                                <div
                                    class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm font-medium text-gray-900 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    {{
                                        prosesLabel[form.proses] ?? form.proses
                                    }}
                                </div>
                            </div>
                            <div v-else>
                                <InputLabel value="Proses" />
                                <div
                                    class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-400 dark:bg-gray-700 dark:text-gray-500"
                                >
                                    -
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Batch Number" />
                                <div
                                    class="mt-1 flex items-center justify-between rounded-md bg-gray-50 px-3 py-2 font-mono text-sm text-gray-900 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    <span>{{ form.batch_number || '-' }}</span>
                                    <span
                                        v-if="selectedWeeklyPlan?.odoo_mo_id"
                                        class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-300"
                                    >
                                        Odoo MO #{{ selectedWeeklyPlan.odoo_mo_id }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Weekly Plan Info Banner -->
                    <div
                        v-if="selectedWeeklyPlan"
                        class="rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/30"
                    >
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p
                                    class="text-sm font-medium text-indigo-800 dark:text-indigo-200"
                                >
                                    Weekly Plan / Batch Odoo Ditemukan
                                </p>
                                <p
                                    class="mt-1 text-xs text-indigo-700 dark:text-indigo-300"
                                >
                                    Proses
                                    <span class="font-semibold">{{
                                        prosesLabel[selectedWeeklyPlan.proses] ?? selectedWeeklyPlan.proses
                                    }}</span>
                                    — Batch
                                    <span class="font-mono font-semibold">{{
                                        selectedWeeklyPlan.batch_number
                                    }}</span>
                                    <span
                                        v-if="selectedWeeklyPlan.target_output"
                                        class="ml-1 font-semibold text-indigo-900 dark:text-indigo-100"
                                    >
                                        (Target: {{ Number(selectedWeeklyPlan.target_output).toLocaleString('id-ID') }} pcs)
                                    </span>
                                </p>
                            </div>
                            <span
                                v-if="selectedWeeklyPlan.odoo_mo_id"
                                class="mt-1 inline-flex w-fit items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-semibold text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200 sm:mt-0"
                            >
                                Odoo MO #{{ selectedWeeklyPlan.odoo_mo_id }}
                            </span>
                        </div>
                    </div>

                    <!-- Mesin & Line -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                        >
                            Mesin & Line
                        </h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Mesin" />
                                <select
                                    v-model="form.mesin_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    required
                                >
                                    <option value="" disabled>
                                        Pilih Mesin
                                    </option>
                                    <option
                                        v-for="m in mesins"
                                        :key="m.id"
                                        :value="m.id"
                                    >
                                        {{ m.nama_mesin }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.mesin_id"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="CT (menit)" />
                                <div
                                    class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm text-gray-900 dark:bg-gray-700 dark:text-gray-100"
                                >
                                    {{ ct > 0 ? ct.toFixed(2) : '-' }}
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Line" />
                                <select
                                    v-model="form.line_id"
                                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    required
                                >
                                    <option value="" disabled>
                                        Pilih Line
                                    </option>
                                    <option
                                        v-for="l in lines"
                                        :key="l.id"
                                        :value="l.id"
                                    >
                                        {{ l.nama_line }}
                                    </option>
                                </select>
                                <InputError
                                    :message="form.errors.line_id"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Capacity & MP -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                            >
                                Target & Capacity
                            </h3>
                            <span
                                v-if="selectedWeeklyPlan?.target_output"
                                class="text-xs font-medium text-indigo-600 dark:text-indigo-400"
                            >
                                Dari Odoo: {{ Number(selectedWeeklyPlan.target_output).toLocaleString('id-ID') }} pcs (Batch: {{ selectedWeeklyPlan.batch_number }})
                            </span>
                        </div>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <div>
                                <InputLabel value="Target Output" />
                                <TextInput
                                    v-model="form.target_mp"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="no-spinner mt-1 block w-full"
                                    required
                                />
                                <InputError
                                    :message="form.errors.target_mp"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="Total MP" />
                                <TextInput
                                    v-model="form.total_mp"
                                    type="number"
                                    min="1"
                                    class="no-spinner mt-1 block w-full"
                                    required
                                />
                                <InputError
                                    :message="form.errors.total_mp"
                                    class="mt-1"
                                />
                            </div>
                            <div>
                                <InputLabel value="Capacity Fisik" />
                                <TextInput
                                    v-model="form.capacity_fisik"
                                    type="number"
                                    min="0"
                                    class="mt-1 block w-full"
                                    readonly
                                    :class="{
                                        'bg-gray-100 dark:bg-gray-800': true,
                                    }"
                                />
                                <p
                                    class="mt-1 text-xs text-gray-500 dark:text-gray-400"
                                >
                                    Otomatis: Target Output × Total MP
                                </p>
                                <InputError
                                    :message="form.errors.capacity_fisik"
                                    class="mt-1"
                                />
                            </div>
                        </div>
                    </div>

                    <PrimaryButton
                        :disabled="form.processing"
                        class="w-full justify-center"
                    >
                        {{
                            form.processing
                                ? 'Menyimpan...'
                                : 'Buat Laporan & Mulai Timer'
                        }}
                    </PrimaryButton>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
