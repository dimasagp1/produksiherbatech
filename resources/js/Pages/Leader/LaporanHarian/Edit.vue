<script setup lang="ts">
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

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
interface DowntimeDetail {
    id: number;
    alasan_downtime_id?: number | null;
    durasi_menit: number;
    keterangan?: string;
    created_at?: string;
    alasanDowntime?: AlasanDowntime;
}

const props = defineProps<{
    laporan: {
        id: number;
        produk_id: number;
        weekly_plan_id: number;
        proses: string;
        batch_number: string;
        mesin_id: number;
        line_id: number;
        tanggal: string;
        shift?: string;
        target_mp: number;
        total_mp: number;
        start_time: string;
        end_time: string;
        capacity_fisik: number;
        output_fisik: number;
        total_pause_menit: number;
        downtimeDetails: DowntimeDetail[];
    };
    downtimeDetails: DowntimeDetail[];
    produks: Produk[];
    mesins: Mesin[];
    lines: Line[];
    alasanDowntimes: AlasanDowntime[];
    totalReject: number;
}>();

const form = useForm({
    produk_id: props.laporan.produk_id,
    weekly_plan_id: props.laporan.weekly_plan_id,
    mesin_id: props.laporan.mesin_id,
    line_id: props.laporan.line_id,
    tanggal: props.laporan.tanggal,
    shift: props.laporan.shift ?? 'shift1',
    target_mp: String(props.laporan.target_mp),
    total_mp: String(props.laporan.total_mp),
    start_time: props.laporan.start_time,
    end_time: props.laporan.end_time,
    capacity_fisik: String(props.laporan.capacity_fisik),
    output_fisik: String(props.laporan.output_fisik),
});

const selectedProduk = ref<Produk | null>(
    props.produks.find((p) => p.id === props.laporan.produk_id) ?? null,
);
const selectedMesin = ref<Mesin | null>(
    props.mesins.find((m) => m.id === props.laporan.mesin_id) ?? null,
);

watch(
    () => form.produk_id,
    (id) => {
        selectedProduk.value =
            props.produks.find((x) => x.id === Number(id)) ?? null;
    },
);

watch(
    () => form.mesin_id,
    (id) => {
        selectedMesin.value =
            props.mesins.find((x) => x.id === Number(id)) ?? null;
    },
);

const grossTime = computed(() => {
    if (!form.start_time || !form.end_time) return 0;
    const [sh, sm] = form.start_time.split(':').map(Number);
    const [eh, em] = form.end_time.split(':').map(Number);
    let start = sh * 60 + sm;
    let end = eh * 60 + em;
    if (end < start) end += 24 * 60;
    return end - start;
});

const totalDowntime = computed(() => props.laporan.total_pause_menit || 0);
const waktuBersih = computed(() =>
    Math.max(0, grossTime.value - totalDowntime.value),
);
const ct = computed(() => selectedMesin.value?.ct ?? 0);
const targetTeoritis = computed(() =>
    ct.value > 0 ? waktuBersih.value * ct.value : 0,
);
const totalRejectCalc = computed(() => props.totalReject || 0);
const totalOutputCalc = computed(
    () => (Number(form.output_fisik) || 0) + totalRejectCalc.value,
);
const availability = computed(() =>
    grossTime.value > 0
        ? Math.min(100, (waktuBersih.value / grossTime.value) * 100)
        : 0,
);
const performance = computed(() =>
    waktuBersih.value > 0
        ? Math.min(
              100,
              (((Number(form.output_fisik) || 0) * ct.value) /
                  waktuBersih.value) *
                  100,
          )
        : 0,
);
const quality = computed(() =>
    totalOutputCalc.value > 0
        ? Math.min(
              100,
              ((Number(form.output_fisik) || 0) / totalOutputCalc.value) * 100,
          )
        : 0,
);
const oee = computed(
    () =>
        (availability.value / 100) *
        (performance.value / 100) *
        (quality.value / 100) *
        100,
);
const produktivitas = computed(() => {
    const targetTotal =
        (Number(form.target_mp) || 0) * (Number(form.total_mp) || 0);
    return targetTotal > 0
        ? ((Number(form.output_fisik) || 0) / targetTotal) * 100
        : 0;
});

function kpiColor(v: number): string {
    if (v >= 85) return 'text-green-600 dark:text-green-400';
    if (v >= 65) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

function formatTime(dateStr?: string): string {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '-';
    const h = String(d.getHours()).padStart(2, '0');
    const m = String(d.getMinutes()).padStart(2, '0');
    const s = String(d.getSeconds()).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

function formatDuration(minutes: number | null | undefined): string {
    if (minutes === null || minutes === undefined || Number(minutes) <= 0) return '0 dtk';
    const totalSeconds = Math.round(Number(minutes) * 60);
    if (totalSeconds < 60) {
        return `${totalSeconds} dtk`;
    }
    const m = Math.floor(totalSeconds / 60);
    const s = totalSeconds % 60;
    if (s === 0) {
        return `${m} mnt`;
    }
    return `${m} mnt ${s} dtk`;
}

function submit() {
    form.put(route('leader.laporan-harian.update', props.laporan.id));
}
</script>

<template>
    <Head title="Edit Laporan Harian" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Edit Laporan Harian
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Perbarui data laporan produksi dan downtime
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
            <form @submit.prevent="submit">
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                    <div class="space-y-6 lg:col-span-2">
                        <!-- Produk & Info -->
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
                                </div>
                                <div>
                                    <InputLabel value="Shift" />
                                    <select
                                        v-model="form.shift"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option value="shift1">Shift 1</option>
                                        <option value="shift2">Shift 2</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Produk" />
                                    <select
                                        v-model="form.produk_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option
                                            v-for="p in produks"
                                            :key="p.id"
                                            :value="p.id"
                                        >
                                            {{ p.nama_produk }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Proses" />
                                    <div
                                        class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {{ laporan.proses }}
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="Batch Number" />
                                    <div
                                        class="mt-1 rounded-md bg-gray-50 px-3 py-2 font-mono text-sm dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {{ laporan.batch_number }}
                                    </div>
                                </div>
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
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option
                                            v-for="m in mesins"
                                            :key="m.id"
                                            :value="m.id"
                                        >
                                            {{ m.nama_mesin }}
                                        </option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="CT" />
                                    <div
                                        class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm font-semibold dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {{ ct.toFixed(2) }} mnt
                                    </div>
                                </div>
                                <div>
                                    <InputLabel value="Line" />
                                    <select
                                        v-model="form.line_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        required
                                    >
                                        <option
                                            v-for="l in lines"
                                            :key="l.id"
                                            :value="l.id"
                                        >
                                            {{ l.nama_line }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Waktu -->
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <h3
                                class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                            >
                                Waktu Produksi
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div>
                                    <InputLabel value="Start Time" />
                                    <TextInput
                                        v-model="form.start_time"
                                        type="time"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="End Time" />
                                    <TextInput
                                        v-model="form.end_time"
                                        type="time"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Gross Time" />
                                    <div
                                        class="mt-1 rounded-md bg-gray-50 px-3 py-2 text-sm font-semibold dark:bg-gray-700 dark:text-gray-100"
                                    >
                                        {{ grossTime }} mnt
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Capacity & Output -->
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <h3
                                class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                            >
                                Capacity & Output
                            </h3>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                                <div>
                                    <InputLabel value="Target MP" />
                                    <TextInput
                                        v-model="form.target_mp"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Total MP" />
                                    <TextInput
                                        v-model="form.total_mp"
                                        type="number"
                                        min="1"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Capacity Fisik" />
                                    <TextInput
                                        v-model="form.capacity_fisik"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Output Fisik" />
                                    <TextInput
                                        v-model="form.output_fisik"
                                        type="number"
                                        min="0"
                                        class="mt-1 block w-full"
                                        required
                                    />
                                </div>
                            </div>
                        </div>

                        <!-- Riwayat Downtime & Jeda Produksi -->
                        <div
                            class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <div
                                class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <h3
                                        class="text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                                    >
                                        Riwayat Downtime & Jeda
                                    </h3>
                                    <span
                                        v-if="
                                            downtimeDetails &&
                                            downtimeDetails.length > 0
                                        "
                                        class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                    >
                                        {{ downtimeDetails.length }} Sesi Jeda
                                    </span>
                                </div>
                                <span
                                    v-if="laporan.total_pause_menit > 0"
                                    class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                >
                                    Total Akumulasi Jeda:
                                    <strong
                                        class="text-amber-600 dark:text-amber-400"
                                    >
                                        {{
                                            formatDuration(
                                                laporan.total_pause_menit,
                                            )
                                        }}
                                    </strong>
                                </span>
                            </div>

                            <div
                                v-if="
                                    downtimeDetails &&
                                    downtimeDetails.length > 0
                                "
                                class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700"
                            >
                                <table
                                    class="min-w-[550px] w-full divide-y divide-gray-200 text-sm dark:divide-gray-700"
                                >
                                    <thead
                                        class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400"
                                    >
                                        <tr>
                                            <th
                                                class="px-4 py-2.5 text-left font-medium"
                                            >
                                                Sesi
                                            </th>
                                            <th
                                                class="px-4 py-2.5 text-left font-medium"
                                            >
                                                Jam Tercatat
                                            </th>
                                            <th
                                                class="px-4 py-2.5 text-left font-medium"
                                            >
                                                Durasi Jeda
                                            </th>
                                            <th
                                                class="px-4 py-2.5 text-left font-medium"
                                            >
                                                Alasan / Keterangan Downtime
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="divide-y divide-gray-100 bg-white dark:divide-gray-700 dark:bg-gray-800"
                                    >
                                        <tr
                                            v-for="(dt, idx) in downtimeDetails"
                                            :key="dt.id || idx"
                                            class="transition hover:bg-gray-50 dark:hover:bg-gray-700/30"
                                        >
                                            <td
                                                class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900 dark:text-gray-100"
                                            >
                                                #{{ idx + 1 }}
                                            </td>
                                            <td
                                                class="whitespace-nowrap px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300"
                                            >
                                                {{ formatTime(dt.created_at) }}
                                            </td>
                                            <td
                                                class="whitespace-nowrap px-4 py-3 font-semibold text-amber-600 dark:text-amber-400"
                                            >
                                                {{
                                                    formatDuration(
                                                        dt.durasi_menit,
                                                    )
                                                }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-gray-700 dark:text-gray-200"
                                            >
                                                <div class="flex flex-col">
                                                    <span
                                                        v-if="
                                                            dt.alasanDowntime
                                                                ?.nama_alasan
                                                        "
                                                        class="text-xs font-medium text-indigo-600 dark:text-indigo-400"
                                                    >
                                                        {{
                                                            dt.alasanDowntime
                                                                .nama_alasan
                                                        }}
                                                    </span>
                                                    <span
                                                        class="font-medium text-gray-800 dark:text-gray-100"
                                                    >
                                                        {{
                                                            dt.keterangan ||
                                                            dt.alasanDowntime
                                                                ?.nama_alasan ||
                                                            '-'
                                                        }}
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div
                                v-else
                                class="rounded-lg border border-dashed border-gray-200 p-6 text-center dark:border-gray-700"
                            >
                                <p
                                    class="text-xs font-medium text-gray-500 dark:text-gray-400"
                                >
                                    Belum ada riwayat downtime/jeda pada laporan
                                    ini.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- KPI Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="sticky top-6 space-y-4">
                            <div
                                class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                            >
                                <h3
                                    class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Kalkulasi
                                </h3>
                                <div class="space-y-3">
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Waktu Bersih</span
                                        >
                                        <span
                                            class="font-medium text-gray-900 dark:text-gray-100"
                                            >{{ waktuBersih }} mnt</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Target Teoritis</span
                                        >
                                        <span
                                            class="font-medium text-gray-900 dark:text-gray-100"
                                            >{{
                                                targetTeoritis.toFixed(0)
                                            }}</span
                                        >
                                    </div>
                                    <hr class="dark:border-gray-700" />
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Quality</span
                                        >
                                        <span
                                            class="font-semibold"
                                            :class="kpiColor(quality)"
                                            >{{ quality.toFixed(1) }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Availability</span
                                        >
                                        <span
                                            class="font-semibold"
                                            :class="kpiColor(availability)"
                                            >{{
                                                availability.toFixed(1)
                                            }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Performance</span
                                        >
                                        <span
                                            class="font-semibold"
                                            :class="kpiColor(performance)"
                                            >{{ performance.toFixed(1) }}%</span
                                        >
                                    </div>
                                    <hr class="dark:border-gray-700" />
                                    <div
                                        class="flex justify-between text-lg font-bold"
                                    >
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >OEE</span
                                        >
                                        <span :class="kpiColor(oee)"
                                            >{{ oee.toFixed(1) }}%</span
                                        >
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span
                                            class="text-gray-600 dark:text-gray-400"
                                            >Produktivitas</span
                                        >
                                        <span
                                            class="font-semibold"
                                            :class="kpiColor(produktivitas)"
                                            >{{
                                                produktivitas.toFixed(1)
                                            }}%</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <PrimaryButton
                                :disabled="form.processing"
                                class="w-full justify-center"
                                >Update Laporan</PrimaryButton
                            >
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </AuthenticatedLayout>
</template>
