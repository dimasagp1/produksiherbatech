<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface DowntimeReason {
    alasan: string;
    durasi: number;
}
interface LaporanDetail {
    id: number;
    proses: string;
    tanggal: string;
    mesin: string;
    line: string;
    leader: string;
    output_fisik: number;
    gross_time_menit: number;
    waktu_bersih_menit: number;
    oee_persen: number;
    downtime_total: number;
    downtime_reasons: DowntimeReason[];
}
interface BatchHistory {
    batch_number: string;
    produk: string;
    tanggal_mulai: string;
    tanggal_selesai: string;
    proses_count: number;
    proses_list: string[];
    total_output: number;
    total_reject: number;
    total_gross_time: number;
    total_waktu_bersih: number;
    total_downtime: number;
    downtime_reasons: Record<string, number>;
    avg_oee: number;
    avg_availability: number;
    avg_performance: number;
    avg_yield: number;
    status: string;
    laporans: LaporanDetail[];
}
interface Summary {
    total_batches: number;
    total_output: number;
    total_reject: number;
    total_downtime: number;
    avg_oee: number;
}

const props = defineProps<{
    batchHistory: BatchHistory[];
    summary: Summary;
    bulan: string;
    search: string;
    shift?: string | null;
}>();

const selectedBulan = ref(props.bulan);
const searchQuery = ref(props.search);
const selectedShift = ref(props.shift ?? '');
const expandedBatch = ref<string | null>(null);

const prosesColor: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    filling:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    packing:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

function kpiColor(v: number): string {
    if (v >= 85) return 'text-green-600 dark:text-green-400';
    if (v >= 65) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

function toggleExpand(batchNumber: string) {
    expandedBatch.value =
        expandedBatch.value === batchNumber ? null : batchNumber;
}

function filterData() {
    router.get(
        route('admin.history.index'),
        {
            bulan: selectedBulan.value,
            search: searchQuery.value,
            shift: selectedShift.value || undefined,
        },
        { preserveState: true },
    );
}
</script>

<template>
    <Head title="History Produksi" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        History Produksi
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Rekapitulasi batch produksi per periode
                    </p>
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-3 sm:px-6 lg:px-8">
            <!-- Filter: Bulan + Search -->
            <div
                class="mb-4 flex flex-col gap-2.5 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex items-center gap-2">
                    <label
                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >Periode:</label
                    >
                    <input
                        v-model="selectedBulan"
                        type="month"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @change="filterData"
                    />
                </div>
                <div class="flex items-center gap-2">
                    <select
                        v-model="selectedShift"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @change="filterData"
                    >
                        <option value="">Semua Shift</option>
                        <option value="shift1">Shift 1</option>
                        <option value="shift2">Shift 2</option>
                    </select>
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Cari batch atau produk..."
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                        @keyup.enter="filterData"
                    />
                    <button
                        @click="filterData"
                        class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-500"
                    >
                        Cari
                    </button>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Total Batch
                    </div>
                    <div
                        class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ summary.total_batches }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Total Output
                    </div>
                    <div
                        class="text-2xl font-bold text-gray-900 dark:text-gray-100"
                    >
                        {{ summary.total_output.toLocaleString() }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Total Reject
                    </div>
                    <div
                        class="text-2xl font-bold text-red-600 dark:text-red-400"
                    >
                        {{ summary.total_reject.toLocaleString() }}
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Total Downtime
                    </div>
                    <div
                        class="text-2xl font-bold text-amber-600 dark:text-amber-400"
                    >
                        {{ summary.total_downtime }} mnt
                    </div>
                </div>
                <div
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
                >
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Avg OEE
                    </div>
                    <div
                        class="text-2xl font-bold"
                        :class="kpiColor(summary.avg_oee)"
                    >
                        {{ summary.avg_oee }}%
                    </div>
                </div>
            </div>

            <!-- Tabel Batch History -->
            <div
                class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <!-- Desktop table -->
                <div class="hidden overflow-x-auto md:block">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr
                                class="border-b border-gray-200 dark:border-gray-700"
                            >
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Batch
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Produk
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Periode
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Proses
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Output
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Reject
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Downtime
                                </th>
                                <th
                                    class="px-4 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Alasan Downtime
                                </th>
                                <th
                                    class="px-4 py-3 text-right text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    OEE
                                </th>
                                <th
                                    class="px-4 py-3 text-center text-xs font-semibold uppercase text-gray-500 dark:text-gray-400"
                                >
                                    Status
                                </th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template
                                v-for="batch in batchHistory"
                                :key="batch.batch_number"
                            >
                                <tr
                                    class="cursor-pointer border-b border-gray-100 transition-colors hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-700/50"
                                    @click="toggleExpand(batch.batch_number)"
                                >
                                    <td
                                        class="px-4 py-3 font-mono font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ batch.batch_number }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-gray-700 dark:text-gray-300"
                                    >
                                        {{ batch.produk }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ formatDate(batch.tanggal_mulai) }} —
                                        {{ formatDate(batch.tanggal_selesai) }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div
                                            class="flex flex-wrap justify-center gap-1"
                                        >
                                            <span
                                                v-for="p in batch.proses_list"
                                                :key="p"
                                                class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                                :class="prosesColor[p]"
                                                >{{ p }}</span
                                            >
                                        </div>
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{
                                            batch.total_output.toLocaleString()
                                        }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right text-red-600 dark:text-red-400"
                                    >
                                        {{ batch.total_reject }}
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-medium text-amber-600 dark:text-amber-400"
                                    >
                                        {{ batch.total_downtime }} mnt
                                    </td>
                                    <td
                                        class="max-w-[200px] truncate px-4 py-3 text-xs text-gray-600 dark:text-gray-400"
                                    >
                                        <span
                                            v-for="(
                                                durasi, alasan
                                            ) in batch.downtime_reasons"
                                            :key="alasan"
                                            class="mr-2"
                                        >
                                            {{ alasan }}: {{ durasi }}m
                                        </span>
                                        <span
                                            v-if="
                                                Object.keys(
                                                    batch.downtime_reasons,
                                                ).length === 0
                                            "
                                            class="text-gray-400"
                                            >-</span
                                        >
                                    </td>
                                    <td
                                        class="px-4 py-3 text-right font-semibold"
                                        :class="kpiColor(batch.avg_oee)"
                                    >
                                        {{ batch.avg_oee }}%
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span
                                            :class="
                                                batch.status === 'locked'
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                                    : 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                            "
                                            class="rounded px-2 py-0.5 text-xs font-medium"
                                        >
                                            {{
                                                batch.status === 'locked'
                                                    ? 'Terkunci'
                                                    : 'Submitted'
                                            }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="text-gray-400">{{
                                            expandedBatch === batch.batch_number
                                                ? '▲'
                                                : '▼'
                                        }}</span>
                                    </td>
                                </tr>
                                <!-- Detail row (expandable) -->
                                <tr v-if="expandedBatch === batch.batch_number">
                                    <td
                                        colspan="11"
                                        class="bg-gray-50 px-4 py-3 dark:bg-gray-700/50"
                                    >
                                        <div class="space-y-2">
                                            <div
                                                v-for="laporan in batch.laporans"
                                                :key="laporan.id"
                                                class="flex flex-wrap items-center gap-3 rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-800"
                                            >
                                                <span
                                                    :class="[
                                                        'rounded px-2 py-0.5 text-xs font-semibold',
                                                        prosesColor[
                                                            laporan.proses
                                                        ],
                                                    ]"
                                                    >{{ laporan.proses }}</span
                                                >
                                                <span
                                                    class="text-xs text-gray-500"
                                                    >{{
                                                        formatDate(
                                                            laporan.tanggal,
                                                        )
                                                    }}</span
                                                >
                                                <span
                                                    class="text-xs text-gray-700 dark:text-gray-300"
                                                    >{{ laporan.mesin }} ·
                                                    {{ laporan.line }}</span
                                                >
                                                <span
                                                    class="text-xs text-gray-500"
                                                    >Leader:
                                                    {{ laporan.leader }}</span
                                                >
                                                <span
                                                    class="text-xs font-medium text-gray-900 dark:text-gray-100"
                                                    >Output:
                                                    {{
                                                        laporan.output_fisik
                                                    }}</span
                                                >
                                                <span
                                                    class="text-xs text-gray-500"
                                                    >Gross:
                                                    {{
                                                        laporan.gross_time_menit
                                                    }}
                                                    mnt</span
                                                >
                                                <span
                                                    class="text-xs text-gray-500"
                                                    >WB:
                                                    {{
                                                        laporan.waktu_bersih_menit
                                                    }}
                                                    mnt</span
                                                >
                                                <span
                                                    class="text-xs font-semibold"
                                                    :class="
                                                        kpiColor(
                                                            laporan.oee_persen,
                                                        )
                                                    "
                                                    >OEE:
                                                    {{
                                                        laporan.oee_persen
                                                    }}%</span
                                                >
                                                <span
                                                    v-if="
                                                        laporan.downtime_total >
                                                        0
                                                    "
                                                    class="text-xs text-amber-600 dark:text-amber-400"
                                                >
                                                    DT:
                                                    {{ laporan.downtime_total }}
                                                    mnt
                                                    <span
                                                        v-for="(
                                                            d, i
                                                        ) in laporan.downtime_reasons"
                                                        :key="i"
                                                        class="ml-1 text-gray-500"
                                                    >
                                                        ({{ d.alasan }}:
                                                        {{ d.durasi }}m)
                                                    </span>
                                                </span>
                                                <Link
                                                    :href="
                                                        route(
                                                            'leader.laporan-harian.show',
                                                            laporan.id,
                                                        )
                                                    "
                                                    class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                                    >Detail →</Link
                                                >
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile cards -->
                <div
                    class="divide-y divide-gray-100 dark:divide-gray-700 md:hidden"
                >
                    <div
                        v-for="batch in batchHistory"
                        :key="batch.batch_number"
                    >
                        <div
                            class="cursor-pointer p-4"
                            @click="toggleExpand(batch.batch_number)"
                        >
                            <div class="flex items-start justify-between">
                                <div>
                                    <div
                                        class="font-mono font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ batch.batch_number }}
                                    </div>
                                    <div
                                        class="mt-0.5 text-xs text-gray-500 dark:text-gray-400"
                                    >
                                        {{ batch.produk }}
                                    </div>
                                    <div class="mt-1 flex flex-wrap gap-1">
                                        <span
                                            v-for="p in batch.proses_list"
                                            :key="p"
                                            class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
                                            :class="prosesColor[p]"
                                            >{{ p }}</span
                                        >
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div
                                        class="font-semibold"
                                        :class="kpiColor(batch.avg_oee)"
                                    >
                                        {{ batch.avg_oee }}%
                                    </div>
                                    <div class="text-[10px] text-gray-400">
                                        OEE
                                    </div>
                                </div>
                            </div>
                            <div
                                class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400"
                            >
                                <span
                                    >Output:
                                    <b
                                        class="text-gray-900 dark:text-gray-100"
                                        >{{ batch.total_output }}</b
                                    ></span
                                >
                                <span
                                    >Reject:
                                    <b class="text-red-600 dark:text-red-400">{{
                                        batch.total_reject
                                    }}</b></span
                                >
                                <span
                                    >DT:
                                    <b
                                        class="text-amber-600 dark:text-amber-400"
                                        >{{ batch.total_downtime }}m</b
                                    ></span
                                >
                                <span
                                    :class="
                                        batch.status === 'locked'
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-blue-600 dark:text-blue-400'
                                    "
                                    >{{
                                        batch.status === 'locked'
                                            ? 'Terkunci'
                                            : 'Submitted'
                                    }}</span
                                >
                            </div>
                            <div
                                v-if="
                                    Object.keys(batch.downtime_reasons).length >
                                    0
                                "
                                class="mt-1 truncate text-[10px] text-gray-400"
                            >
                                <span
                                    v-for="(
                                        durasi, alasan
                                    ) in batch.downtime_reasons"
                                    :key="alasan"
                                    class="mr-2"
                                    >{{ alasan }}: {{ durasi }}m</span
                                >
                            </div>
                        </div>
                        <!-- Expandable detail -->
                        <div
                            v-if="expandedBatch === batch.batch_number"
                            class="space-y-2 bg-gray-50 px-4 pb-4 dark:bg-gray-700/50"
                        >
                            <div
                                v-for="laporan in batch.laporans"
                                :key="laporan.id"
                                class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-600 dark:bg-gray-800"
                            >
                                <div class="mb-1 flex items-center gap-2">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-xs font-semibold',
                                            prosesColor[laporan.proses],
                                        ]"
                                        >{{ laporan.proses }}</span
                                    >
                                    <span class="text-xs text-gray-500">{{
                                        formatDate(laporan.tanggal)
                                    }}</span>
                                    <span
                                        class="ml-auto text-xs font-semibold"
                                        :class="kpiColor(laporan.oee_persen)"
                                        >OEE: {{ laporan.oee_persen }}%</span
                                    >
                                </div>
                                <div
                                    class="space-y-0.5 text-xs text-gray-600 dark:text-gray-400"
                                >
                                    <div>
                                        {{ laporan.mesin }} ·
                                        {{ laporan.line }} · Leader:
                                        {{ laporan.leader }}
                                    </div>
                                    <div>
                                        Output: {{ laporan.output_fisik }} ·
                                        Gross: {{ laporan.gross_time_menit }}m ·
                                        WB: {{ laporan.waktu_bersih_menit }}m
                                    </div>
                                    <div
                                        v-if="laporan.downtime_total > 0"
                                        class="text-amber-600 dark:text-amber-400"
                                    >
                                        DT: {{ laporan.downtime_total }}m
                                        <span
                                            v-for="(
                                                d, i
                                            ) in laporan.downtime_reasons"
                                            :key="i"
                                            class="ml-1 text-gray-500"
                                            >({{ d.alasan }}:
                                            {{ d.durasi }}m)</span
                                        >
                                    </div>
                                </div>
                                <Link
                                    :href="
                                        route(
                                            'leader.laporan-harian.show',
                                            laporan.id,
                                        )
                                    "
                                    class="mt-1 inline-block text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                    >Detail →</Link
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Empty state -->
                <div
                    v-if="batchHistory.length === 0"
                    class="py-12 text-center text-gray-500 dark:text-gray-400"
                >
                    Tidak ada data history untuk periode ini.
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
