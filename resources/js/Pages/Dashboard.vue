<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref, watch } from 'vue';

const page = usePage() as any;
const currentRole = computed(() => page.props.auth?.user?.role ?? 'leader');
function hasRole(roles: string[]): boolean {
    return roles.includes(currentRole.value);
}
const isPpic = computed(() => hasRole(['ppic', 'superadmin']));
const isLeaderLike = computed(() =>
    hasRole(['leader', 'spv', 'superadmin', 'admin', 'manager']),
);
const isAdminLike = computed(() => hasRole(['superadmin', 'admin']));

interface Produk {
    id: number;
    nama_produk: string;
}
interface Line {
    id: number;
    nama_line: string;
}
interface Laporan {
    id: number;
    batch_number: string;
    proses: string;
    oee_persen: number;
    output_fisik: number;
    produktivitas_persen: number;
    yield_persen: number;
    produk: Produk;
    line: Line;
    tanggal: string;
    mesin?: { nama_mesin: string };
    user?: { name: string };
    total_reject?: number;
    available_qty?: number;
    sisa_qty?: number;
    reject_jenis?: string | null;
}

const props = defineProps<{
    currentPlan: {
        produk: Produk;
        proses: string;
        batch_number: string;
        tanggal: string;
    } | null;
    todayLaporans: Laporan[];
    selectedDate: string;
    selectedShift?: string | null;
    isLive: boolean;
    thresholds: {
        produktivitas: number;
        oee: number;
        yield: number;
        reject_rate_warning: number;
        heat: { high: number; medium: number; low: number };
    };
    kpis: {
        oee: number;
        yield: number;
        produktivitas: number;
        downtime: number;
        availability: number;
        performance: number;
        gross: number;
        waktuBersih: number;
    };
    rejectStats: {
        total: number;
        rate: number;
        byJenis: Record<string, number>;
        byProduk: Record<string, number>;
        totalOutput: number;
    };
    rejectByJenis: Record<string, number>;
    monthlyReject: Record<string, number>;
    downtimePareto: Record<string, number>;
    heatData: Record<string, Record<string, number>>;
    heatLines: string[];
    monitoringByProses: Record<
        string,
        {
            count: number;
            avg_oee: number;
            total_output: number;
            total_reject: number;
        }
    >;
    monitoringByProduk: Record<
        string,
        {
            produk: string;
            count: number;
            avg_oee: number;
            total_output: number;
            total_reject: number;
        }
    >;
    batchBreakdown: Array<{
        batch_number: string;
        produk: string;
        proses: string;
        total_output: number;
        oee: number;
        yield: number;
        total_reject: number;
        sisa_qty: number;
    }>;
    monthlyOutput: Record<string, number>;
    summary: {
        leader: string;
        produk: string;
        proses: string;
        batch: string;
        line: string;
        mesin: string;
    } | null;
    masterStats?: {
        produk: number;
        produk_total: number;
        mesin: number;
        line: number;
        alasan_downtime: number;
    } | null;
    weeklyPlanStats?: {
        aktif: number;
        draft: number;
        hari_ini: number;
        minggu_ini: number;
    } | null;
}>();

const fLine = ref('');
const fProses = ref('');
const fProduk = ref('');
const fJenis = ref('');
const dateInput = ref(props.selectedDate);
const shiftInput = ref(props.selectedShift ?? '');

watch(
    () => props.selectedDate,
    (v) => {
        dateInput.value = v;
    },
);
watch(
    () => props.selectedShift,
    (v) => {
        shiftInput.value = v ?? '';
    },
);

function filteredLaporans() {
    return props.todayLaporans.filter((l) => {
        const okLine = !fLine.value || l.line?.nama_line === fLine.value;
        const okProses = !fProses.value || l.proses === fProses.value;
        const okProduk =
            !fProduk.value || l.produk?.nama_produk === fProduk.value;
        const okJenis =
            !fJenis.value || (l.reject_jenis ?? '') === fJenis.value;
        return okLine && okProses && okProduk && okJenis;
    });
}

function kpiDeltaClass(v: number, target: number) {
    return v >= target ? 'up' : 'down';
}

const monthNames = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'Mei',
    'Jun',
    'Jul',
    'Agu',
    'Sep',
    'Okt',
    'Nov',
    'Des',
];

function heatColor(v: number) {
    const h = props.thresholds?.heat ?? { high: 7000, medium: 5000, low: 3000 };
    if (v === 0) return '#6B7280';
    if (v >= h.high) return '#059669';
    if (v >= h.medium) return '#0891B2';
    if (v >= h.low) return '#B45309';
    return '#DC2626';
}

function goLive() {
    router.get(
        route('dashboard'),
        { shift: shiftInput.value || undefined },
        { preserveState: false },
    );
}
function goHistory() {
    if (!dateInput.value) return;
    router.get(
        route('dashboard'),
        { tanggal: dateInput.value, shift: shiftInput.value || undefined },
        { preserveState: false },
    );
}

declare const Chart: any;

let chartInstances: any[] = [];
function destroyCharts() {
    chartInstances.forEach((c) => {
        try {
            c.destroy();
        } catch {
            /* chart sudah dihancurkan */
        }
    });
    chartInstances = [];
}

function initCharts() {
    destroyCharts();
    if (typeof Chart === 'undefined') return;
    const css = getComputedStyle(document.documentElement);
    const muted = css.getPropertyValue('--text-muted').trim() || '#5B667A';
    const border = css.getPropertyValue('--border').trim() || '#D8DEEA';
    const accentA = css.getPropertyValue('--accent-a').trim() || '#0891B2';
    const accentB = css.getPropertyValue('--accent-b').trim() || '#7C3AED';
    const good = css.getPropertyValue('--good').trim() || '#059669';
    const danger = '#DC2626';
    Chart.defaults.font.family = "'IBM Plex Sans', sans-serif";
    Chart.defaults.font.size = 10.5;
    Chart.defaults.color = muted;

    const paretoEl = document.getElementById(
        'chartPareto',
    ) as HTMLCanvasElement | null;
    if (paretoEl) {
        const labels = Object.keys(props.downtimePareto);
        const data = Object.values(props.downtimePareto) as number[];
        const total = data.reduce((a, b) => a + b, 0) || 1;
        let run = 0;
        const cum = data.map((v) => {
            run += v;
            return +((run / total) * 100).toFixed(1);
        });
        const c = new Chart(paretoEl, {
            data: {
                labels,
                datasets: [
                    {
                        type: 'bar',
                        label: 'Menit',
                        data,
                        backgroundColor: accentA,
                        borderRadius: 3,
                        order: 2,
                        yAxisID: 'y',
                    },
                    {
                        type: 'line',
                        label: 'Kumulatif %',
                        data: cum,
                        borderColor: accentB,
                        backgroundColor: accentB,
                        tension: 0.3,
                        order: 1,
                        yAxisID: 'y1',
                        pointRadius: 3,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        labels: { boxWidth: 10, padding: 10 },
                    },
                },
                scales: {
                    y: {
                        grid: { color: border },
                        title: { display: true, text: 'menit' },
                    },
                    y1: {
                        position: 'right',
                        min: 0,
                        max: 100,
                        grid: { display: false },
                        title: { display: true, text: '%' },
                    },
                    x: { grid: { display: false } },
                },
            },
        });
        chartInstances.push(c);
    }

    const prodEl = document.getElementById(
        'chartProduk',
    ) as HTMLCanvasElement | null;
    if (prodEl) {
        const prodLabels = Object.values(props.monitoringByProduk).map(
            (v) => v.produk,
        );
        const prodData = Object.values(props.monitoringByProduk).map(
            (v) => v.total_output,
        );
        const rejectData = Object.values(props.monitoringByProduk).map(
            (v) => v.total_reject,
        );
        const hasReject = rejectData.some((v) => v > 0);
        const palette = prodLabels.map(
            (_, i) =>
                `hsl(${(i * 360) / Math.max(prodLabels.length, 1)},70%,50%)`,
        );
        const bg = prodLabels.length <= 3 ? [accentA, accentB, good] : palette;
        if (hasReject) {
            const c = new Chart(prodEl, {
                type: 'bar',
                data: {
                    labels: prodLabels,
                    datasets: [
                        {
                            label: 'Output',
                            data: prodData,
                            backgroundColor: bg as any,
                            borderRadius: 4,
                        },
                        {
                            label: 'Reject',
                            data: rejectData,
                            backgroundColor: danger,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true, labels: { boxWidth: 10 } },
                    },
                    scales: {
                        x: { grid: { color: border }, stacked: false },
                        y: { grid: { display: false }, stacked: false },
                    },
                },
            });
            chartInstances.push(c);
        } else {
            const c = new Chart(prodEl, {
                type: 'bar',
                data: {
                    labels: prodLabels,
                    datasets: [
                        {
                            data: prodData,
                            backgroundColor: bg as any,
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { color: border } },
                        y: { grid: { display: false } },
                    },
                },
            });
            chartInstances.push(c);
        }
    }

    // Reject per Jenis donut
    const rejectEl = document.getElementById(
        'chartRejectJenis',
    ) as HTMLCanvasElement | null;
    if (rejectEl) {
        const labelsRaw = Object.keys(props.rejectByJenis);
        const labels = labelsRaw.map(
            (k) =>
                ({ sublayer: 'Sublayer', ga: 'GA', process: 'Proses' })[k] ?? k,
        );
        const data = Object.values(props.rejectByJenis) as number[];
        const colors = labelsRaw.map((k) =>
            k === 'sublayer' ? accentA : k === 'ga' ? '#F59E0B' : accentB,
        );
        if (labels.length === 0) {
            // empty state handled via HTML, skip chart
        } else {
            const c = new Chart(rejectEl, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [
                        { data, backgroundColor: colors, borderWidth: 1 },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { boxWidth: 12, padding: 10 },
                        },
                    },
                },
            });
            chartInstances.push(c);
        }
    }

    const bulanEl = document.getElementById(
        'chartBulan',
    ) as HTMLCanvasElement | null;
    if (bulanEl) {
        const months = Array.from(
            new Set([
                ...Object.keys(props.monthlyOutput),
                ...Object.keys(props.monthlyReject),
            ]),
        ).sort((a, b) => Number(a) - Number(b));
        const labels = months.map((m) => monthNames[Number(m) - 1]);
        const dataOut = months.map((m) => props.monthlyOutput[m] ?? 0);
        const dataRej = months.map((m) => props.monthlyReject[m] ?? 0);
        const c = new Chart(bulanEl, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Output',
                        data: dataOut,
                        borderColor: accentA,
                        backgroundColor: 'rgba(8,145,178,.15)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                    },
                    {
                        label: 'Reject',
                        data: dataRej,
                        borderColor: danger,
                        backgroundColor: 'rgba(220,38,38,.08)',
                        fill: false,
                        tension: 0.35,
                        pointRadius: 3,
                        borderDash: [4, 3],
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, labels: { boxWidth: 12 } },
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { grid: { color: border } },
                },
            },
        });
        chartInstances.push(c);
    }
}

watch(
    () => [
        props.downtimePareto,
        props.monitoringByProduk,
        props.monthlyOutput,
        props.rejectByJenis,
        props.monthlyReject,
    ],
    () => {
        setTimeout(initCharts, 50);
    },
);

onMounted(() => {
    if (typeof (window as any).Chart === 'undefined') {
        const s = document.createElement('script');
        s.src =
            'https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js';
        s.onload = initCharts;
        document.head.appendChild(s);
    } else {
        initCharts();
    }
});
</script>

<template>
    <Head title="Dashboard" />
    <AuthenticatedLayout>
        <div class="mx-auto max-w-7xl px-3 sm:px-6">
            <div class="topbar">
                <div>
                    <h1>Dashboard Produksi</h1>
                    <div class="sub">
                        Mengikuti Weekly Plan aktif ·
                        <span class="mono">{{
                            currentPlan
                                ? new Date(
                                      currentPlan.tanggal,
                                  ).toLocaleDateString('id-ID', {
                                      day: 'numeric',
                                      month: 'short',
                                      year: 'numeric',
                                  })
                                : new Date(selectedDate).toLocaleDateString(
                                      'id-ID',
                                      {
                                          day: 'numeric',
                                          month: 'short',
                                          year: 'numeric',
                                      },
                                  )
                        }}</span>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 14px">
                    <span
                        class="live-pill"
                        :style="{ opacity: isLive ? 1 : 0.6 }"
                        ><span
                            class="dot"
                            :style="{
                                background: isLive ? 'var(--good)' : '#9CA3AF',
                            }"
                        ></span
                        >{{ isLive ? 'LIVE' : 'HISTORY' }}</span
                    >
                    <span style="color: var(--text-muted); font-size: 12px"
                        >{{ todayLaporans.length }} laporan
                        {{
                            isLive
                                ? 'hari ini'
                                : 'pada ' +
                                  new Date(selectedDate).toLocaleDateString(
                                      'id-ID',
                                  )
                        }}</span
                    >
                </div>
            </div>

            <div
                class="panel"
                style="
                    display: flex;
                    align-items: center;
                    gap: 12px;
                    margin-bottom: 14px;
                    flex-wrap: wrap;
                "
            >
                <div style="display: flex; align-items: center; gap: 8px">
                    <button
                        @click="goLive"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                            isLive
                                ? 'bg-indigo-600 text-white dark:bg-indigo-500'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        ]"
                    >
                        ● Live
                    </button>
                    <button
                        @click="goHistory"
                        :class="[
                            'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold transition',
                            !isLive
                                ? 'bg-amber-500 text-white'
                                : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                        ]"
                    >
                        ○ History
                    </button>
                </div>
                <input
                    type="date"
                    v-model="dateInput"
                    class="rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                />
                <select
                    v-model="shiftInput"
                    class="rounded-md border border-gray-300 px-2 py-1.5 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    @change="goHistory"
                >
                    <option value="">Semua Shift</option>
                    <option value="shift1">Shift 1</option>
                    <option value="shift2">Shift 2</option>
                </select>
                <button
                    @click="goHistory"
                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 dark:bg-indigo-500"
                >
                    Tampilkan
                </button>
                <span
                    v-if="!isLive"
                    style="font-size: 11px; color: var(--text-muted)"
                    >Menampilkan data tanggal
                    {{
                        new Date(selectedDate).toLocaleDateString('id-ID', {
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })
                    }}
                    ·
                    <a
                        @click="goLive"
                        style="
                            color: var(--accent-a);
                            cursor: pointer;
                            font-weight: 600;
                        "
                        >Kembali ke Hari Ini</a
                    ></span
                >
            </div>

            <div class="summary-bar">
                <div class="facts">
                    <span
                        >Leader · <b>{{ summary?.leader ?? '-' }}</b></span
                    >
                    <span
                        >Produk · <b>{{ summary?.produk ?? '-' }}</b></span
                    >
                    <span
                        >Proses ·
                        <b style="text-transform: capitalize">{{
                            summary?.proses ?? '-'
                        }}</b></span
                    >
                    <span
                        >Batch ·
                        <b class="mono">{{ summary?.batch ?? '-' }}</b></span
                    >
                    <span
                        >Line · <b>{{ summary?.line ?? '-' }}</b></span
                    >
                    <span
                        >Mesin · <b>{{ summary?.mesin ?? '-' }}</b></span
                    >
                </div>
                <div class="live-toggle" style="gap: 8px">
                    <span style="font-size: 11px; color: var(--text-muted)"
                        >Plan:
                        {{
                            currentPlan
                                ? currentPlan.produk?.nama_produk +
                                  ' · ' +
                                  currentPlan.proses
                                : 'Tidak ada plan'
                        }}</span
                    >
                    <span
                        v-if="isPpic && weeklyPlanStats"
                        style="font-size: 11px; color: var(--text-muted)"
                        >· {{ weeklyPlanStats.aktif }} aktif /
                        {{ weeklyPlanStats.draft }} draft</span
                    >
                </div>
            </div>

            <div
                v-if="masterStats || weeklyPlanStats"
                class="g-4 grid"
                style="margin-bottom: 14px"
            >
                <div
                    v-if="weeklyPlanStats"
                    class="panel"
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: space-between;
                    "
                >
                    <div>
                        <div class="label">Weekly Plan Aktif</div>
                        <div class="kpi-value">{{ weeklyPlanStats.aktif }}</div>
                        <div
                            class="hint"
                            style="font-size: 11px; color: var(--text-muted)"
                        >
                            Hari ini {{ weeklyPlanStats.hari_ini }} · Minggu ini
                            {{ weeklyPlanStats.minggu_ini }}
                        </div>
                    </div>
                    <Link
                        :href="route('ppic.weekly-plan.index')"
                        style="
                            font-size: 12px;
                            color: var(--accent-a);
                            font-weight: 600;
                        "
                        >Kelola →</Link
                    >
                </div>
                <div v-if="masterStats" class="panel">
                    <div class="label">Master Produk</div>
                    <div class="kpi-value">
                        {{ masterStats.produk
                        }}<small
                            style="font-size: 12px; color: var(--text-muted)"
                        >
                            / {{ masterStats.produk_total }}</small
                        >
                    </div>
                    <div
                        class="hint"
                        style="font-size: 11px; color: var(--text-muted)"
                    >
                        Aktif / total
                    </div>
                </div>
                <div v-if="isAdminLike && masterStats" class="panel">
                    <div class="label">Mesin · Line</div>
                    <div class="kpi-value" style="font-size: 20px">
                        {{ masterStats.mesin }} mesin ·
                        {{ masterStats.line }} line
                    </div>
                    <div
                        class="hint"
                        style="font-size: 11px; color: var(--text-muted)"
                    >
                        {{ masterStats.alasan_downtime }} alasan downtime
                    </div>
                </div>
            </div>

            <!-- KPI 5 — P0: Produktivitas/OEE/Downtime/Yield + Total Reject -->
            <div
                v-if="isLeaderLike"
                class="g-5 grid"
                style="
                    margin-bottom: 14px;
                    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                    display: grid;
                    gap: 14px;
                "
            >
                <div class="panel kpi">
                    <div class="label">Produktivitas</div>
                    <div class="kpi-value">
                        {{ kpis.produktivitas.toFixed(1) }}<sup>%</sup>
                    </div>
                    <div
                        class="kpi-delta"
                        :class="
                            kpiDeltaClass(
                                kpis.produktivitas,
                                thresholds.produktivitas,
                            )
                        "
                    >
                        {{
                            kpis.produktivitas >= thresholds.produktivitas
                                ? '▲'
                                : '▼'
                        }}
                        {{
                            (
                                kpis.produktivitas - thresholds.produktivitas
                            ).toFixed(1)
                        }}
                        pt vs target {{ thresholds.produktivitas }}%
                    </div>
                    <span class="formula">Output ÷ (Target/MP × Total MP)</span>
                </div>
                <div class="panel kpi">
                    <div class="label">OEE</div>
                    <div class="kpi-value">
                        {{ kpis.oee.toFixed(1) }}<sup>%</sup>
                    </div>
                    <div
                        class="kpi-delta"
                        :class="kpiDeltaClass(kpis.oee, thresholds.oee)"
                    >
                        {{ kpis.oee >= thresholds.oee ? '▲' : '▼' }}
                        {{ (kpis.oee - thresholds.oee).toFixed(1) }} pt vs
                        target {{ thresholds.oee }}%
                    </div>
                    <span class="formula"
                        >Availability {{ kpis.availability.toFixed(1) }}% ×
                        Performance {{ kpis.performance.toFixed(1) }}% × Yield
                        {{ kpis.yield.toFixed(1) }}%</span
                    >
                </div>
                <div class="panel kpi">
                    <div class="label">Downtime</div>
                    <div class="kpi-value">
                        {{ kpis.downtime }}<sup>mnt</sup>
                    </div>
                    <div class="kpi-delta down">
                        {{
                            ((kpis.downtime / (kpis.gross || 1)) * 100).toFixed(
                                1,
                            )
                        }}% dari Gross {{ kpis.gross }} mnt
                    </div>
                    <span class="formula"
                        >Gross {{ kpis.gross }} − Waktu Bersih
                        {{ kpis.waktuBersih }} = {{ kpis.downtime }} mnt</span
                    >
                </div>
                <div class="panel kpi">
                    <div class="label">Yield</div>
                    <div class="kpi-value">
                        {{ kpis.yield.toFixed(1) }}<sup>%</sup>
                    </div>
                    <div class="kpi-delta up">
                        {{
                            kpis.yield >= thresholds.yield
                                ? '▲ Optimal'
                                : '▼ Perlu perhatian'
                        }}
                    </div>
                    <span class="formula">Output ÷ Capacity</span>
                </div>
                <div class="panel kpi" style="border-color: var(--border)">
                    <div class="label">Total Reject</div>
                    <div
                        class="kpi-value"
                        :style="{
                            color:
                                rejectStats.rate >
                                thresholds.reject_rate_warning
                                    ? '#DC2626'
                                    : 'var(--text)',
                        }"
                    >
                        {{ rejectStats.total.toLocaleString('id-ID')
                        }}<sup style="font-size: 11px; font-weight: 600">
                            unit</sup
                        >
                    </div>
                    <div
                        class="kpi-delta"
                        :class="
                            rejectStats.rate > thresholds.reject_rate_warning
                                ? 'down'
                                : 'up'
                        "
                    >
                        {{ rejectStats.rate.toFixed(2) }}% rate · dari
                        {{ rejectStats.totalOutput.toLocaleString('id-ID') }}
                        output
                    </div>
                    <span class="formula"
                        >sublayer
                        {{
                            (rejectStats.byJenis.sublayer ?? 0).toLocaleString(
                                'id-ID',
                            )
                        }}
                        · GA
                        {{
                            (rejectStats.byJenis.ga ?? 0).toLocaleString(
                                'id-ID',
                            )
                        }}
                        · Proses
                        {{
                            (rejectStats.byJenis.process ?? 0).toLocaleString(
                                'id-ID',
                            )
                        }}</span
                    >
                </div>
            </div>

            <!-- Pareto + OEE + Reject Jenis — P0 -->
            <div
                v-if="isLeaderLike"
                class="g-3 grid"
                style="margin-bottom: 14px"
            >
                <div class="panel">
                    <div class="panel-head">
                        <h3>Downtime per Alasan — Pareto</h3>
                        <span class="hint">durasi 0 disaring</span>
                    </div>
                    <div
                        v-if="Object.keys(downtimePareto).length === 0"
                        style="
                            height: 180px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: var(--text-muted);
                            font-size: 12px;
                        "
                    >
                        Belum ada downtime tercatat
                    </div>
                    <div v-else style="height: 230px">
                        <canvas id="chartPareto"></canvas>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head"><h3>OEE Breakdown</h3></div>
                    <div class="gauge-wrap">
                        <svg width="180" height="110" viewBox="0 0 180 110">
                            <path
                                d="M14 100 A76 76 0 0 1 166 100"
                                fill="none"
                                stroke="var(--border)"
                                stroke-width="14"
                                stroke-linecap="round"
                            />
                            <path
                                :d="'M14 100 A76 76 0 0 1 166 100'"
                                fill="none"
                                stroke="url(#gg)"
                                stroke-width="14"
                                stroke-linecap="round"
                                :stroke-dasharray="`${Math.round((kpis.oee / 100) * 239)} 239`"
                            />
                            <defs>
                                <linearGradient
                                    id="gg"
                                    x1="0"
                                    y1="0"
                                    x2="1"
                                    y2="0"
                                >
                                    <stop
                                        offset="0%"
                                        stop-color="var(--accent-a)"
                                    />
                                    <stop
                                        offset="100%"
                                        stop-color="var(--accent-b)"
                                    />
                                </linearGradient>
                            </defs>
                            <text
                                x="90"
                                y="88"
                                text-anchor="middle"
                                font-family="JetBrains Mono"
                                font-size="26"
                                font-weight="700"
                                :fill="'var(--text)'"
                            >
                                {{ kpis.oee.toFixed(1) }}%
                            </text>
                        </svg>
                        <div class="gauge-sub">
                            <div class="item">
                                <div class="n" style="color: var(--good)">
                                    {{ kpis.availability.toFixed(1) }}%
                                </div>
                                Availability
                            </div>
                            <div class="item">
                                <div class="n" style="color: var(--accent-a)">
                                    {{ kpis.performance.toFixed(1) }}%
                                </div>
                                Performance
                            </div>
                            <div class="item">
                                <div class="n" style="color: var(--accent-b)">
                                    {{ kpis.yield.toFixed(1) }}%
                                </div>
                                Yield
                            </div>
                        </div>
                    </div>
                </div>
                <div class="panel">
                    <div class="panel-head">
                        <h3>Reject per Jenis</h3>
                        <span class="hint"
                            >sublayer / GA / Proses —
                            {{ rejectStats.total }} unit</span
                        >
                    </div>
                    <div
                        v-if="Object.keys(rejectByJenis).length === 0"
                        style="
                            height: 180px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: var(--text-muted);
                            font-size: 12px;
                        "
                    >
                        Belum ada reject pada
                        {{
                            isLive
                                ? 'hari ini'
                                : new Date(selectedDate).toLocaleDateString(
                                      'id-ID',
                                  )
                        }}
                    </div>
                    <div v-else style="height: 230px">
                        <canvas id="chartRejectJenis"></canvas>
                    </div>
                </div>
            </div>

            <!-- Heat + breakdown -->
            <div class="g-2 grid" style="margin-bottom: 14px">
                <div v-if="isLeaderLike" class="panel">
                    <div class="panel-head">
                        <h3>Monitoring per Proses & Line</h3>
                        <span class="hint"
                            >Output
                            {{
                                isLive
                                    ? 'hari ini'
                                    : new Date(selectedDate).toLocaleDateString(
                                          'id-ID',
                                      )
                            }}</span
                        >
                    </div>
                    <div class="scrollbar-none w-full overflow-x-auto pb-1">
                        <div class="heat-grid">
                            <div></div>
                            <div class="collabel">Mixing</div>
                            <div class="collabel">Filling</div>
                            <div class="collabel">Packing</div>
                            <template v-for="ln in heatLines" :key="ln">
                                <div class="rowlabel">{{ ln }}</div>
                                <div
                                    class="hcell"
                                    :style="{
                                        background: heatColor(
                                            heatData[ln]?.mixing ?? 0,
                                        ),
                                    }"
                                >
                                    {{ heatData[ln]?.mixing ?? 0 }}
                                </div>
                                <div
                                    class="hcell"
                                    :style="{
                                        background: heatColor(
                                            heatData[ln]?.filling ?? 0,
                                        ),
                                    }"
                                >
                                    {{ heatData[ln]?.filling ?? 0 }}
                                </div>
                                <div
                                    class="hcell"
                                    :style="{
                                        background: heatColor(
                                            heatData[ln]?.packing ?? 0,
                                        ),
                                    }"
                                >
                                    {{
                                        heatData[ln]?.packing !== undefined
                                            ? heatData[ln]?.packing || '—'
                                            : '—'
                                    }}
                                </div>
                            </template>
                        </div>
                    </div>

                    <div class="panel-head" style="margin-top: 16px">
                        <h3>Monitoring per Produk</h3>
                        <span class="hint">Output & Reject per produk</span>
                    </div>
                    <div
                        v-if="Object.keys(monitoringByProduk).length === 0"
                        style="
                            height: 100px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: var(--text-muted);
                            font-size: 12px;
                        "
                    >
                        Belum ada output produk pada
                        {{
                            isLive
                                ? 'hari ini'
                                : new Date(selectedDate).toLocaleDateString(
                                      'id-ID',
                                  )
                        }}
                    </div>
                    <div v-else style="height: 150px">
                        <canvas id="chartProduk"></canvas>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-head">
                        <h3>Breakdown per Batch Number</h3>
                        <span class="hint">{{
                            isLive
                                ? 'Hari berjalan'
                                : new Date(selectedDate).toLocaleDateString(
                                      'id-ID',
                                  )
                        }}</span>
                    </div>

                    <!-- Desktop Table View -->
                    <div class="table-wrap hidden md:block">
                        <table>
                            <thead>
                                <tr>
                                    <th>Batch</th>
                                    <th>Produk</th>
                                    <th>Proses</th>
                                    <th class="num">Output</th>
                                    <th class="num">Reject</th>
                                    <th class="num">Sisa</th>
                                    <th class="num">Yield</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="val in batchBreakdown"
                                    :key="val.batch_number"
                                >
                                    <td class="mono font-semibold">
                                        {{ val.batch_number }}
                                    </td>
                                    <td>{{ val.produk }}</td>
                                    <td style="text-transform: capitalize">
                                        {{ val.proses }}
                                    </td>
                                    <td class="num mono">
                                        {{
                                            (
                                                val.total_output ?? 0
                                            ).toLocaleString('id-ID')
                                        }}
                                    </td>
                                    <td
                                        class="num mono"
                                        :style="{
                                            color:
                                                val.total_reject > 0
                                                    ? '#DC2626'
                                                    : 'var(--text-muted)',
                                            fontWeight:
                                                val.total_reject > 0
                                                    ? 600
                                                    : 400,
                                        }"
                                    >
                                        {{
                                            (
                                                val.total_reject ?? 0
                                            ).toLocaleString('id-ID')
                                        }}
                                    </td>
                                    <td class="num mono">
                                        {{
                                            (val.sisa_qty ?? 0).toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                    </td>
                                    <td
                                        class="num mono font-semibold"
                                        style="color: var(--good)"
                                    >
                                        {{ (val.yield ?? 0).toFixed(1) }}%
                                    </td>
                                </tr>
                                <tr v-if="batchBreakdown.length === 0">
                                    <td
                                        colspan="7"
                                        style="
                                            text-align: center;
                                            color: var(--text-muted);
                                        "
                                    >
                                        Belum ada data
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Card View -->
                    <div class="block space-y-2 md:hidden">
                        <div
                            v-if="batchBreakdown.length === 0"
                            class="py-6 text-center text-xs text-gray-500 dark:text-gray-400"
                        >
                            Belum ada data batch
                        </div>
                        <div
                            v-for="val in batchBreakdown"
                            :key="val.batch_number"
                            class="rounded-lg border border-gray-100 bg-gray-50/70 p-2.5 dark:border-gray-700/60 dark:bg-gray-900/60"
                        >
                            <div class="mb-1 flex items-center justify-between">
                                <span
                                    class="font-mono text-xs font-bold text-gray-900 dark:text-white"
                                    >{{ val.batch_number }}</span
                                >
                                <span
                                    class="inline-flex items-center rounded bg-blue-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-blue-700 dark:bg-blue-950 dark:text-blue-300"
                                >
                                    {{ val.proses }}
                                </span>
                            </div>
                            <div
                                class="mb-2 text-[11.5px] font-medium text-gray-700 dark:text-gray-300"
                            >
                                {{ val.produk }}
                            </div>
                            <div
                                class="grid grid-cols-4 gap-1 rounded border border-gray-100 bg-white p-1.5 text-center text-[10.5px] dark:border-gray-700/40 dark:bg-gray-800"
                            >
                                <div>
                                    <div
                                        class="text-[9px] font-semibold uppercase text-gray-400"
                                    >
                                        Output
                                    </div>
                                    <div
                                        class="font-mono font-bold text-gray-900 dark:text-gray-100"
                                    >
                                        {{
                                            (
                                                val.total_output ?? 0
                                            ).toLocaleString('id-ID')
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[9px] font-semibold uppercase text-gray-400"
                                    >
                                        Reject
                                    </div>
                                    <div
                                        class="font-mono font-bold"
                                        :class="
                                            val.total_reject > 0
                                                ? 'text-red-600 dark:text-red-400'
                                                : 'text-gray-500'
                                        "
                                    >
                                        {{
                                            (
                                                val.total_reject ?? 0
                                            ).toLocaleString('id-ID')
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[9px] font-semibold uppercase text-gray-400"
                                    >
                                        Sisa
                                    </div>
                                    <div
                                        class="font-mono font-bold text-gray-900 dark:text-gray-100"
                                    >
                                        {{
                                            (val.sisa_qty ?? 0).toLocaleString(
                                                'id-ID',
                                            )
                                        }}
                                    </div>
                                </div>
                                <div>
                                    <div
                                        class="text-[9px] font-semibold uppercase text-gray-400"
                                    >
                                        Yield
                                    </div>
                                    <div
                                        class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                    >
                                        {{ (val.yield ?? 0).toFixed(1) }}%
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel-head" style="margin-top: 16px">
                        <h3>Breakdown per Bulan</h3>
                        <span class="hint"
                            >Output vs Reject, tahun berjalan</span
                        >
                    </div>
                    <div
                        v-if="
                            Object.keys(monthlyOutput).length === 0 &&
                            Object.keys(monthlyReject).length === 0
                        "
                        style="
                            height: 80px;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            color: var(--text-muted);
                            font-size: 12px;
                        "
                    >
                        Belum ada data bulanan
                    </div>
                    <div v-else style="height: 130px">
                        <canvas id="chartBulan"></canvas>
                    </div>
                </div>
            </div>

            <!-- Lembar Detail — P1: + kolom Reject/Jenis & filter Jenis -->
            <div v-if="isLeaderLike" class="panel">
                <div class="panel-head">
                    <h3>Lembar Detail</h3>
                    <span class="hint"
                        >Bisa difilter multi-dimensi ({{
                            filteredLaporans().length
                        }}
                        baris)</span
                    >
                </div>
                <div class="filters" style="flex-wrap: wrap">
                    <select v-model="fLine">
                        <option value="">Semua Line</option>
                        <option v-for="ln in heatLines" :key="ln" :value="ln">
                            {{ ln }}
                        </option>
                    </select>
                    <select v-model="fProses">
                        <option value="">Semua Proses</option>
                        <option>mixing</option>
                        <option>filling</option>
                        <option>packing</option>
                    </select>
                    <select v-model="fProduk">
                        <option value="">Semua Produk</option>
                        <option
                            v-for="p in [
                                ...new Set(
                                    todayLaporans.map(
                                        (l) => l.produk?.nama_produk,
                                    ),
                                ),
                            ]"
                            :key="p"
                            :value="p"
                        >
                            {{ p }}
                        </option>
                    </select>
                    <select v-model="fJenis">
                        <option value="">Semua Jenis Reject</option>
                        <option value="sublayer">Sublayer</option>
                        <option value="ga">GA</option>
                        <option value="process">Proses</option>
                        <option value="">— Tanpa Reject</option>
                    </select>
                </div>

                <!-- Desktop Table View -->
                <div class="table-wrap hidden md:block">
                    <table id="detailTable">
                        <thead>
                            <tr>
                                <th>Tanggal</th>
                                <th>Line</th>
                                <th>Mesin</th>
                                <th>Proses</th>
                                <th>Produk</th>
                                <th>Batch</th>
                                <th class="num">Output</th>
                                <th class="num">Reject</th>
                                <th>Jenis</th>
                                <th class="num">Sisa</th>
                                <th class="num">Yield</th>
                                <th class="num">OEE</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="lap in filteredLaporans()"
                                :key="lap.id"
                                :data-line="lap.line?.nama_line"
                                :data-proses="lap.proses"
                                :data-produk="lap.produk?.nama_produk"
                            >
                                <td>
                                    {{
                                        new Date(
                                            lap.tanggal,
                                        ).toLocaleDateString('id-ID', {
                                            day: 'numeric',
                                            month: 'short',
                                        })
                                    }}
                                </td>
                                <td>{{ lap.line?.nama_line }}</td>
                                <td>{{ lap.mesin?.nama_mesin ?? '-' }}</td>
                                <td style="text-transform: capitalize">
                                    {{ lap.proses }}
                                </td>
                                <td>{{ lap.produk?.nama_produk }}</td>
                                <td class="mono font-semibold">
                                    {{ lap.batch_number }}
                                </td>
                                <td class="num mono">
                                    {{
                                        lap.output_fisik?.toLocaleString(
                                            'id-ID',
                                        ) ?? '-'
                                    }}
                                </td>
                                <td
                                    class="num mono"
                                    :style="{
                                        color:
                                            (lap.total_reject ?? 0) > 0
                                                ? '#DC2626'
                                                : 'var(--text-muted)',
                                    }"
                                >
                                    {{
                                        (lap.total_reject ?? 0).toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </td>
                                <td
                                    class="mono"
                                    style="
                                        font-size: 11px;
                                        text-transform: capitalize;
                                    "
                                >
                                    {{ lap.reject_jenis ?? '—' }}
                                </td>
                                <td class="num mono">
                                    {{
                                        (lap.sisa_qty ?? 0).toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </td>
                                <td class="num mono">
                                    {{
                                        lap.yield_persen != null
                                            ? lap.yield_persen.toFixed(1) + '%'
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="num mono font-semibold"
                                    style="color: var(--good)"
                                >
                                    {{
                                        lap.oee_persen != null
                                            ? lap.oee_persen.toFixed(1) + '%'
                                            : '-'
                                    }}
                                </td>
                            </tr>
                            <tr v-if="filteredLaporans().length === 0">
                                <td
                                    colspan="12"
                                    style="
                                        text-align: center;
                                        color: var(--text-muted);
                                    "
                                >
                                    Tidak ada data untuk filter ini
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card View -->
                <div class="block space-y-2 md:hidden">
                    <div
                        v-if="filteredLaporans().length === 0"
                        class="py-6 text-center text-xs text-gray-500 dark:text-gray-400"
                    >
                        Tidak ada data untuk filter ini
                    </div>
                    <div
                        v-for="lap in filteredLaporans()"
                        :key="lap.id"
                        class="rounded-lg border border-gray-100 bg-gray-50/70 p-2.5 dark:border-gray-700/60 dark:bg-gray-900/60"
                    >
                        <div class="mb-1 flex items-center justify-between">
                            <span
                                class="font-mono text-xs font-bold text-gray-900 dark:text-white"
                                >{{ lap.batch_number }}</span
                            >
                            <span
                                class="inline-flex items-center rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                {{ lap.proses }}
                            </span>
                        </div>
                        <div
                            class="mb-0.5 text-[11.5px] font-semibold text-gray-800 dark:text-gray-200"
                        >
                            {{ lap.produk?.nama_produk }}
                        </div>
                        <div
                            class="mb-2 flex items-center gap-1.5 text-[10.5px] text-gray-500 dark:text-gray-400"
                        >
                            <span>{{ lap.line?.nama_line ?? '-' }}</span>
                            <span>•</span>
                            <span>{{ lap.mesin?.nama_mesin ?? '-' }}</span>
                            <span>•</span>
                            <span>{{
                                new Date(lap.tanggal).toLocaleDateString(
                                    'id-ID',
                                    { day: 'numeric', month: 'short' },
                                )
                            }}</span>
                        </div>
                        <div
                            class="grid grid-cols-4 gap-1 rounded border border-gray-100 bg-white p-1.5 text-center text-[10.5px] dark:border-gray-700/40 dark:bg-gray-800"
                        >
                            <div>
                                <div
                                    class="text-[9px] font-semibold uppercase text-gray-400"
                                >
                                    Output
                                </div>
                                <div
                                    class="font-mono font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        lap.output_fisik?.toLocaleString(
                                            'id-ID',
                                        ) ?? '-'
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[9px] font-semibold uppercase text-gray-400"
                                >
                                    Reject
                                </div>
                                <div
                                    class="font-mono font-bold"
                                    :class="
                                        (lap.total_reject ?? 0) > 0
                                            ? 'text-red-600 dark:text-red-400'
                                            : 'text-gray-500'
                                    "
                                >
                                    {{
                                        (lap.total_reject ?? 0).toLocaleString(
                                            'id-ID',
                                        )
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[9px] font-semibold uppercase text-gray-400"
                                >
                                    Yield
                                </div>
                                <div
                                    class="font-mono font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    {{
                                        lap.yield_persen != null
                                            ? lap.yield_persen.toFixed(1) + '%'
                                            : '-'
                                    }}
                                </div>
                            </div>
                            <div>
                                <div
                                    class="text-[9px] font-semibold uppercase text-gray-400"
                                >
                                    OEE
                                </div>
                                <div
                                    class="font-mono font-bold text-indigo-600 dark:text-indigo-400"
                                >
                                    {{
                                        lap.oee_persen != null
                                            ? lap.oee_persen.toFixed(1) + '%'
                                            : '-'
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <footer class="note">
                Dashboard LinePulse ·
                {{
                    isLive
                        ? 'LIVE mengikuti Weekly Plan aktif'
                        : 'HISTORY ' +
                          new Date(selectedDate).toLocaleDateString('id-ID')
                }}
                · Data terhubung langsung ke DB (filtered submitted/locked untuk
                bulanan) · Termasuk monitoring Reject Produk · Fase 1 MVP
            </footer>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.live-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
}
</style>
