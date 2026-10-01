<script setup lang="ts">
import DangerButton from '@/Components/DangerButton.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate } from '@/utils/date';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

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
    id?: number;
    alasan_downtime_id: number;
    durasi_menit: number;
    tipe_input: string;
    keterangan?: string;
    enabled?: boolean;
    alasanDowntime?: AlasanDowntime;
}

interface Laporan {
    id: number;
    tanggal: string;
    proses: string;
    batch_number: string;
    start_time: string;
    start_time_at: string | null;
    end_time: string | null;
    gross_time_menit: number | null;
    target_mp: number;
    total_mp: number;
    capacity_fisik: number;
    output_fisik: number | null;
    waktu_bersih_menit: number | null;
    target_teoritis: number | null;
    yield_persen: number | null;
    availability_persen: number | null;
    performance_persen: number | null;
    oee_persen: number | null;
    produktivitas_persen: number | null;
    mesin_id?: number;
    line_id?: number;
    ct: number;
    status: string;
    timer_status: string;
    total_pause_menit: number;
    pause_started_at: string | null;
    user?: { id: number; name: string };
    produk?: { id: number; nama_produk: string };
    mesin?: { id: number; nama_mesin: string; ct: number };
    line?: { id: number; nama_line: string };
    downtimeDetails?: {
        id?: number;
        durasi_menit: number;
        keterangan?: string;
        alasanDowntime?: AlasanDowntime;
    }[];
}

const props = defineProps<{
    laporan: Laporan;
    downtimeDetails: DowntimeDetail[];
    alasanDowntimes: AlasanDowntime[];
    siblingLaporans: Laporan[];
    weeklyPlans: {
        id: number;
        produk_id: number;
        tanggal: string;
        proses: string;
        batch_number: string;
    }[];
    mesins?: Mesin[];
    lines?: Line[];
    totalReject: number;
}>();

// Timer state for current laporan
const timerRunning = ref(false);
const timerElapsed = ref(0);
let timerInterval: number | null = null;

// Downtime live counter (saat pause)
const downtimeElapsed = ref(0);
let downtimeInterval: number | null = null;

// Alasan downtime input (wajib saat pause)
const alasanDowntime = ref('');

// Output fisik for current laporan
const outputFisik = ref<string>(String(props.laporan.output_fisik ?? ''));

// Timer display for current
const timerDisplay = computed(() => {
    const h = Math.floor(timerElapsed.value / 3600);
    const m = Math.floor((timerElapsed.value % 3600) / 60);
    const s = timerElapsed.value % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

// Downtime display (live saat pause)
const downtimeDisplay = computed(() => {
    const h = Math.floor(downtimeElapsed.value / 3600);
    const m = Math.floor((downtimeElapsed.value % 3600) / 60);
    const s = downtimeElapsed.value % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

// Progress
const progressSteps = ['Draft', 'Berjalan', 'Downtime', 'Selesai', 'Terkirim'];
const currentStep = computed(() => {
    if (
        props.laporan.status === 'submitted' ||
        props.laporan.status === 'locked'
    )
        return 4;
    if (props.laporan.timer_status === 'end') return 3;
    if (props.laporan.timer_status === 'pause') return 2;
    if (props.laporan.timer_status === 'start') return 1;
    return 0;
});
const progressPercent = computed(() => (currentStep.value / 4) * 100);

const isFinished = computed(
    () =>
        props.laporan.status === 'submitted' ||
        props.laporan.status === 'locked',
);

// Dependency checks
const processOrder: Record<string, number> = {
    mixing: 0,
    filling: 1,
    packing: 2,
};

const isMixingFinished = computed(() => {
    // mixing finished = timer_status end OR submitted (backend now allows end)
    const mixingSibling = props.siblingLaporans.find(
        (s) => s.proses === 'mixing',
    );
    if (mixingSibling)
        return (
            mixingSibling.timer_status === 'end' ||
            mixingSibling.status === 'submitted'
        );
    // if current is mixing and finished, also true for filling check when current is mixing
    if (props.laporan.proses === 'mixing')
        return (
            props.laporan.timer_status === 'end' ||
            props.laporan.status === 'submitted'
        );
    return false;
});

// All processes for this product + date
const allProcesses = computed(() => {
    const current = {
        proses: props.laporan.proses,
        hasLaporan: true,
        timer_status: props.laporan.timer_status,
        status: props.laporan.status,
    };
    const siblings = props.siblingLaporans.map((s) => ({
        proses: s.proses,
        hasLaporan: true,
        timer_status: s.timer_status,
        status: s.status,
    }));
    return [current, ...siblings].sort(
        (a, b) =>
            (processOrder[a.proses] ?? 99) - (processOrder[b.proses] ?? 99),
    );
});

const hasNextProcess = computed(() => {
    const currentOrder = processOrder[props.laporan.proses] ?? -1;
    const existingProcesses = new Set([
        props.laporan.proses,
        ...props.siblingLaporans.map((s) => s.proses),
    ]);
    return props.weeklyPlans.some(
        (wp) =>
            (processOrder[wp.proses] ?? 99) > currentOrder &&
            !existingProcesses.has(wp.proses),
    );
});

const nextProcessPlan = computed(() => {
    if (!hasNextProcess.value) return null;
    const currentOrder = processOrder[props.laporan.proses] ?? -1;
    const existingProcesses = new Set([
        props.laporan.proses,
        ...props.siblingLaporans.map((s) => s.proses),
    ]);
    return (
        props.weeklyPlans
            .filter(
                (wp) =>
                    (processOrder[wp.proses] ?? 99) > currentOrder &&
                    !existingProcesses.has(wp.proses),
            )
            .sort(
                (a, b) =>
                    (processOrder[a.proses] ?? 99) -
                    (processOrder[b.proses] ?? 99),
            )[0] ?? null
    );
});

// Rangkaian: all laporans sorted by process order — for single-page display
const rangkaian = computed(() => {
    const all = [props.laporan, ...props.siblingLaporans].sort(
        (a, b) =>
            (processOrder[a.proses] ?? 99) - (processOrder[b.proses] ?? 99),
    );
    return all;
});

// Helper: check if rangkaian has both mixing and filling
const isRangkaian = computed(() => {
    // if weeklyPlans has 2 for same product+tanggal, it's rangkaian
    return props.weeklyPlans.length >= 2 || props.siblingLaporans.length >= 1;
});

const fillingLaporan = computed(() => {
    if (props.laporan.proses === 'filling') return props.laporan;
    return props.siblingLaporans.find((s) => s.proses === 'filling') ?? null;
});

const mixingLaporan = computed(() => {
    if (props.laporan.proses === 'mixing') return props.laporan;
    return props.siblingLaporans.find((s) => s.proses === 'mixing') ?? null;
});

// For filling Start guard: filling can start only if mixing is end/submitted
const canStartFilling = computed(() => {
    const mixing = mixingLaporan.value;
    if (!mixing) return true; // no mixing plan → no block
    return (
        mixing.timer_status === 'end' ||
        mixing.status === 'submitted' ||
        mixing.status === 'locked'
    );
});

// Calculations for current laporan (real-time)
// Editable process parameters
const targetMp = ref<number | string>(props.laporan.target_mp ?? '');
const totalMp = ref<number | string>(props.laporan.total_mp ?? '1');
const mesinId = ref<number | string>(props.laporan.mesin_id ?? '');
const lineId = ref<number | string>(props.laporan.line_id ?? '');
const capacityFisik = ref<number | string>(props.laporan.capacity_fisik ?? '');

watch(
    () => props.laporan,
    (newLaporan) => {
        targetMp.value = newLaporan.target_mp ?? '';
        totalMp.value = newLaporan.total_mp ?? '1';
        mesinId.value = newLaporan.mesin_id ?? '';
        lineId.value = newLaporan.line_id ?? '';
        capacityFisik.value = newLaporan.capacity_fisik ?? '';
        outputFisik.value = String(newLaporan.output_fisik ?? '');
    },
);

// CT calculation based on chosen mesin:
const currentMesin = computed(
    () =>
        (props.mesins ?? []).find((m) => m.id === Number(mesinId.value)) ??
        props.laporan.mesin,
);
const ctVal = computed(() => currentMesin.value?.ct ?? props.laporan.ct ?? 0);

// Auto capacity:
watch([targetMp, totalMp], () => {
    const t = parseFloat(String(targetMp.value)) || 0;
    const mp = parseInt(String(totalMp.value)) || 0;
    if (t > 0 && mp > 0) {
        capacityFisik.value = String(t * mp);
    }
});

const grossTimeCalc = computed(() => {
    if (!props.laporan.start_time) return 0;
    if (
        props.laporan.gross_time_menit !== null &&
        props.laporan.gross_time_menit !== undefined &&
        (isFinished.value || props.laporan.timer_status === 'end')
    ) {
        return props.laporan.gross_time_menit;
    }
    if (isFinished.value && props.laporan.gross_time_menit)
        return props.laporan.gross_time_menit;
    return Math.floor(timerElapsed.value / 60);
});

const totalDowntimeCalc = computed(() => props.laporan.total_pause_menit || 0);
const waktuBersihCalc = computed(() =>
    Math.max(0, grossTimeCalc.value - totalDowntimeCalc.value),
);
const totalRejectCalc = computed(() => props.totalReject || 0);
const totalOutputCalc = computed(
    () => (Number(outputFisik.value) || 0) + totalRejectCalc.value,
);
const availabilityCalc = computed(() =>
    grossTimeCalc.value > 0
        ? Math.min(100, (waktuBersihCalc.value / grossTimeCalc.value) * 100)
        : 0,
);
const performanceCalc = computed(() =>
    waktuBersihCalc.value > 0
        ? Math.min(
              100,
              (((Number(outputFisik.value) || 0) * ctVal.value) /
                  waktuBersihCalc.value) *
                  100,
          )
        : 0,
);
const qualityCalc = computed(() =>
    totalOutputCalc.value > 0
        ? Math.min(
              100,
              ((Number(outputFisik.value) || 0) / totalOutputCalc.value) * 100,
          )
        : 0,
);
const oeeCalc = computed(
    () =>
        (availabilityCalc.value / 100) *
        (performanceCalc.value / 100) *
        (qualityCalc.value / 100) *
        100,
);
const produktivitasCalc = computed(() => {
    const targetTotal =
        (Number(targetMp.value) || props.laporan.target_mp || 0) *
        (Number(totalMp.value) || props.laporan.total_mp || 0);
    return targetTotal > 0
        ? ((Number(outputFisik.value) || 0) / targetTotal) * 100
        : 0;
});

const showKpi = computed(() => {
    if (isFinished.value && props.laporan.oee_persen !== null) {
        return {
            grossTime: props.laporan.gross_time_menit ?? 0,
            waktuBersih: props.laporan.waktu_bersih_menit ?? 0,
            targetTeoritis: props.laporan.target_teoritis ?? 0,
            quality: props.laporan.yield_persen ?? 0,
            availability: props.laporan.availability_persen ?? 0,
            performance: props.laporan.performance_persen ?? 0,
            oee: props.laporan.oee_persen ?? 0,
            produktivitas: props.laporan.produktivitas_persen ?? 0,
        };
    }
    return {
        grossTime: grossTimeCalc.value,
        waktuBersih: waktuBersihCalc.value,
        targetTeoritis: waktuBersihCalc.value * ctVal.value,
        quality: qualityCalc.value,
        availability: availabilityCalc.value,
        performance: performanceCalc.value,
        oee: oeeCalc.value,
        produktivitas: produktivitasCalc.value,
    };
});

function kpiColor(v: number): string {
    if (v >= 85) return 'text-green-600 dark:text-green-400';
    if (v >= 65) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
};
const statusColor: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
    submitted: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    locked: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
};

// Timer API calls for current
function startTimer() {
    router.post(route('leader.laporan-harian.timer.start', props.laporan.id));
}
function pauseTimer() {
    router.post(route('leader.laporan-harian.timer.pause', props.laporan.id));
}
function resumeTimer() {
    router.post(route('leader.laporan-harian.timer.start', props.laporan.id), {
        alasan_downtime: alasanDowntime.value,
    });
}
function endTimer() {
    const payload: Record<string, any> = {};
    if (props.laporan.timer_status === 'pause') {
        payload.alasan_downtime = alasanDowntime.value;
    }
    router.post(
        route('leader.laporan-harian.timer.end', props.laporan.id),
        payload,
    );
}
function submitLaporan() {
    router.post(route('leader.laporan-harian.submit', props.laporan.id), {
        mesin_id: Number(mesinId.value) || props.laporan.mesin_id,
        line_id: Number(lineId.value) || props.laporan.line_id,
        target_mp: Number(targetMp.value) || props.laporan.target_mp,
        total_mp: Number(totalMp.value) || props.laporan.total_mp,
        capacity_fisik:
            Number(capacityFisik.value) || props.laporan.capacity_fisik,
        output_fisik: Number(outputFisik.value) || 0,
    });
}

// Generic actions for sibling (stay on same page via back)
function startTimerFor(id: number) {
    router.post(route('leader.laporan-harian.timer.start', id));
}
function pauseTimerFor(id: number) {
    router.post(route('leader.laporan-harian.timer.pause', id));
}
function resumeTimerFor(id: number) {
    router.post(route('leader.laporan-harian.timer.start', id), {
        alasan_downtime: alasanDowntime.value,
    });
}
function endTimerFor(id: number) {
    const payload: Record<string, any> = {};
    const sibling = props.siblingLaporans.find((s) => s.id === id);
    if (sibling && sibling.timer_status === 'pause') {
        payload.alasan_downtime = alasanDowntime.value;
    }
    router.post(route('leader.laporan-harian.timer.end', id), payload);
}

function parseTimeToSeconds(t: string): number {
    const parts = t.split(':').map(Number);
    const h = parts[0] ?? 0,
        m = parts[1] ?? 0,
        s = parts[2] ?? 0;
    return h * 3600 + m * 60 + s;
}
function startTimerLoop() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    timerRunning.value = true;
    if (timerElapsed.value === 0) {
        const now = new Date();
        if (props.laporan.start_time_at) {
            const startAt = new Date(props.laporan.start_time_at);
            const diffSec = Math.floor(
                (now.getTime() - startAt.getTime()) / 1000,
            );
            const pauseSeconds = (props.laporan.total_pause_menit || 0) * 60;
            timerElapsed.value = Math.max(0, diffSec - pauseSeconds);
        } else if (props.laporan.start_time) {
            const startSeconds = parseTimeToSeconds(props.laporan.start_time);
            const nowSeconds =
                now.getHours() * 3600 +
                now.getMinutes() * 60 +
                now.getSeconds();
            let diff = nowSeconds - startSeconds;
            if (diff < 0) diff += 24 * 3600;
            const pauseSeconds = (props.laporan.total_pause_menit || 0) * 60;
            timerElapsed.value = Math.max(0, diff - pauseSeconds);
        }
    }
    timerInterval = window.setInterval(() => {
        timerElapsed.value++;
    }, 1000);
}

function stopTimerLoop() {
    timerRunning.value = false;
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

function startDowntimeLoop() {
    stopDowntimeLoop();
    const previousPause = (props.laporan.total_pause_menit || 0) * 60;
    if (props.laporan.pause_started_at) {
        const pauseAt = new Date(props.laporan.pause_started_at);
        const now = new Date();
        const sessionElapsed = Math.floor(
            (now.getTime() - pauseAt.getTime()) / 1000,
        );
        downtimeElapsed.value = sessionElapsed + previousPause;
    } else {
        downtimeElapsed.value = previousPause;
    }
    downtimeInterval = window.setInterval(() => {
        downtimeElapsed.value++;
    }, 1000);
}

function stopDowntimeLoop() {
    if (downtimeInterval) {
        clearInterval(downtimeInterval);
        downtimeInterval = null;
    }
}

function lock() {
    if (confirm('Kunci laporan ini? Setelah dikunci tidak bisa diedit.')) {
        router.post(route('leader.laporan-harian.lock', props.laporan.id));
    }
}

onMounted(() => {
    handleTimerStatus();
});

watch(
    () => props.laporan.timer_status,
    () => {
        handleTimerStatus();
    },
);

function handleTimerStatus() {
    const status = props.laporan.timer_status;
    if (status === 'start' && props.laporan.status !== 'locked') {
        stopDowntimeLoop();
        downtimeElapsed.value = 0;
        startTimerLoop();
    } else if (status === 'pause') {
        stopTimerLoop();
        // Freeze main timer at pause point
        if (timerElapsed.value === 0) {
            if (props.laporan.start_time_at) {
                const pauseAt = props.laporan.pause_started_at
                    ? new Date(props.laporan.pause_started_at)
                    : new Date();
                const startAt = new Date(props.laporan.start_time_at);
                const diffSec = Math.floor(
                    (pauseAt.getTime() - startAt.getTime()) / 1000,
                );
                const totalPause = (props.laporan.total_pause_menit || 0) * 60;
                timerElapsed.value = Math.max(0, diffSec - totalPause);
            } else if (props.laporan.start_time) {
                const pauseAt = props.laporan.pause_started_at
                    ? new Date(props.laporan.pause_started_at)
                    : new Date();
                const startSeconds = parseTimeToSeconds(
                    props.laporan.start_time,
                );
                const pauseSecondsAt =
                    pauseAt.getHours() * 3600 +
                    pauseAt.getMinutes() * 60 +
                    pauseAt.getSeconds();
                let diff = pauseSecondsAt - startSeconds;
                if (diff < 0) diff += 24 * 3600;
                const totalPause = (props.laporan.total_pause_menit || 0) * 60;
                timerElapsed.value = Math.max(0, diff - totalPause);
            }
        }
        // Start live downtime counter
        startDowntimeLoop();
    } else if (status === 'end') {
        stopTimerLoop();
        stopDowntimeLoop();
        downtimeElapsed.value = 0;
        if (
            props.laporan.gross_time_menit !== null &&
            props.laporan.gross_time_menit !== undefined
        ) {
            timerElapsed.value = props.laporan.gross_time_menit * 60;
        } else if (props.laporan.start_time && props.laporan.end_time) {
            let startSec = parseTimeToSeconds(props.laporan.start_time);
            let endSec = parseTimeToSeconds(props.laporan.end_time);
            let diff = endSec - startSec;
            if (diff < 0) diff += 24 * 3600;
            const totalPause = (props.laporan.total_pause_menit || 0) * 60;
            timerElapsed.value = Math.max(0, diff - totalPause);
        }
    } else {
        stopTimerLoop();
        stopDowntimeLoop();
        downtimeElapsed.value = 0;
    }
}

onUnmounted(() => {
    stopTimerLoop();
    stopDowntimeLoop();
});

function isLaporanFinished(l: Laporan) {
    return l.status === 'submitted' || l.status === 'locked';
}
function laporanTimerLabel(l: Laporan) {
    if (l.timer_status === 'start') return 'Berjalan';
    if (l.timer_status === 'pause') return 'Dijeda';
    if (l.timer_status === 'end') return 'Selesai';
    return 'Draft';
}
</script>

<template>
    <Head title="Detail Laporan Harian" />

    <AuthenticatedLayout>
        <template #header>
            <div
                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
            >
                <div>
                    <h2
                        class="text-lg font-bold leading-tight text-gray-800 dark:text-gray-200 sm:text-xl"
                    >
                        Detail Laporan Harian
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Tracking timer proses, downtime, output fisik &
                        kalkulasi OEE
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton
                        v-if="!isFinished && laporan.status !== 'locked'"
                        @click="lock"
                        class="py-1.5 text-xs"
                        >Kunci Laporan</SecondaryButton
                    >
                    <Link
                        :href="route('leader.laporan-harian.index')"
                        class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-300"
                        >Kembali</Link
                    >
                </div>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-3 sm:px-6 lg:px-8">
            <!-- Progress Bar -->
            <div
                class="mb-5 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
                <div class="mb-2 flex items-center justify-between">
                    <span
                        class="text-sm font-medium text-gray-700 dark:text-gray-300"
                        >Progres: {{ progressSteps[currentStep] }}</span
                    >
                    <span class="text-xs text-gray-500 dark:text-gray-400"
                        >{{ currentStep }}/4</span
                    >
                </div>
                <div
                    class="h-2.5 w-full rounded-full bg-gray-200 dark:bg-gray-700"
                >
                    <div
                        class="h-2.5 rounded-full bg-indigo-600 transition-all duration-500"
                        :style="{ width: progressPercent + '%' }"
                    ></div>
                </div>
                <div class="mt-1 flex justify-between">
                    <span
                        v-for="(step, i) in progressSteps"
                        :key="i"
                        class="text-[10px]"
                        :class="
                            i <= currentStep
                                ? 'font-semibold text-indigo-600'
                                : 'text-gray-400'
                        "
                        >{{ step }}</span
                    >
                </div>
            </div>

            <!-- Rangkaian Info — Satu halaman untuk Mixing + Filling -->
            <div
                v-if="isRangkaian"
                class="mb-6 rounded-lg border border-indigo-200 bg-indigo-50 p-4 dark:border-indigo-800 dark:bg-indigo-900/20"
            >
                <h3
                    class="mb-1 text-sm font-semibold text-indigo-800 dark:text-indigo-200"
                >
                    Rangkaian Produksi: {{ laporan.produk?.nama_produk }} —
                    {{ formatDate(laporan.tanggal) }}
                </h3>
                <p class="mb-3 text-xs text-indigo-700 dark:text-indigo-300">
                    Satu halaman untuk {{ weeklyPlans.length }} proses pada
                    tanggal yang sama. Mixing harus selesai sebelum Filling
                    dapat dimulai.
                </p>
                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="proc in allProcesses"
                        :key="proc.proses"
                        class="flex items-center gap-1.5 rounded-md border border-indigo-100 bg-white px-3 py-1.5 dark:border-indigo-800 dark:bg-gray-800"
                    >
                        <span
                            v-if="
                                proc.hasLaporan &&
                                (proc.status === 'submitted' ||
                                    proc.timer_status === 'end')
                            "
                            class="text-green-600"
                            >✓</span
                        >
                        <span
                            v-else-if="
                                proc.hasLaporan && proc.timer_status === 'start'
                            "
                            class="text-blue-600"
                            >●</span
                        >
                        <span v-else-if="proc.hasLaporan" class="text-gray-600"
                            >○</span
                        >
                        <span v-else class="text-gray-400">○</span>
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ prosesLabel[proc.proses] ?? proc.proses }}</span
                        >
                        <span
                            v-if="proc.hasLaporan"
                            class="text-[10px] text-gray-500"
                            >{{
                                proc.status === 'submitted'
                                    ? 'Selesai'
                                    : proc.timer_status === 'end'
                                      ? 'Selesai'
                                      : proc.timer_status === 'start'
                                        ? 'Berjalan'
                                        : proc.timer_status === 'pause'
                                          ? 'Dijeda'
                                          : 'Draft'
                            }}</span
                        >
                        <span v-else class="text-[10px] italic text-gray-400"
                            >Belum ada laporan</span
                        >
                    </div>
                </div>
            </div>

            <!-- Sibling Laporans — Parallel processes overview (legacy amber) -->
            <div
                v-if="
                    (siblingLaporans.length > 0 || hasNextProcess) &&
                    !isRangkaian
                "
                class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-900/20"
            >
                <h3
                    class="mb-2 text-sm font-semibold text-amber-800 dark:text-amber-200"
                >
                    Proses Paralel: {{ laporan.produk?.nama_produk }}
                </h3>
                <p class="mb-3 text-xs text-amber-700 dark:text-amber-300">
                    Produk ini memiliki proses lain pada tanggal yang sama.
                </p>
                <div class="mb-3 flex flex-wrap gap-2">
                    <div
                        v-for="proc in allProcesses"
                        :key="proc.proses"
                        class="flex items-center gap-1.5 rounded-md border border-amber-100 bg-white px-3 py-1.5 dark:border-amber-800 dark:bg-gray-800"
                    >
                        <span
                            v-if="
                                proc.hasLaporan &&
                                (proc.status === 'submitted' ||
                                    proc.timer_status === 'end')
                            "
                            class="text-green-600"
                            >✓</span
                        >
                        <span v-else-if="proc.hasLaporan" class="text-blue-600"
                            >●</span
                        >
                        <span v-else class="text-gray-400">○</span>
                        <span
                            class="text-sm font-medium text-gray-700 dark:text-gray-300"
                            >{{ prosesLabel[proc.proses] ?? proc.proses }}</span
                        >
                        <span
                            v-if="proc.hasLaporan"
                            class="text-[10px] text-gray-500"
                            >{{
                                proc.status === 'submitted'
                                    ? 'Selesai'
                                    : proc.timer_status === 'end'
                                      ? 'End'
                                      : proc.timer_status === 'start'
                                        ? 'Berjalan'
                                        : 'Draft'
                            }}</span
                        >
                        <span v-else class="text-[10px] italic text-gray-400"
                            >Belum ada</span
                        >
                    </div>
                </div>
                <div v-if="siblingLaporans.length > 0" class="space-y-2">
                    <div
                        v-for="sibling in siblingLaporans"
                        :key="sibling.id"
                        class="flex items-center justify-between rounded-md border border-amber-100 bg-white p-3 dark:border-amber-800 dark:bg-gray-800"
                    >
                        <div class="flex items-center gap-3">
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-xs font-medium',
                                    sibling.timer_status === 'end' ||
                                    sibling.status === 'submitted'
                                        ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200'
                                        : sibling.timer_status === 'start'
                                          ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                          : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
                                ]"
                                >{{
                                    prosesLabel[sibling.proses] ??
                                    sibling.proses
                                }}</span
                            >
                            <span
                                class="text-sm text-gray-600 dark:text-gray-400"
                                >{{ sibling.batch_number }}</span
                            >
                            <span class="text-xs text-gray-400">·</span>
                            <span class="text-xs text-gray-500"
                                >Status:
                                {{
                                    sibling.timer_status === 'end'
                                        ? 'Selesai'
                                        : sibling.timer_status === 'start'
                                          ? 'Berjalan'
                                          : sibling.timer_status === 'pause'
                                            ? 'Dijeda'
                                            : sibling.timer_status
                                }}</span
                            >
                        </div>
                        <Link
                            :href="
                                route('leader.laporan-harian.show', sibling.id)
                            "
                            class="text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                            >Lihat →</Link
                        >
                    </div>
                </div>
                <div
                    v-if="laporan.proses === 'filling'"
                    class="mt-3 rounded bg-amber-100 p-2 dark:bg-amber-900/30"
                >
                    <p
                        v-if="!canStartFilling"
                        class="text-xs text-amber-800 dark:text-amber-200"
                    >
                        ⚠️ Mixing belum selesai. Tunggu Mixing selesai sebelum
                        mulai Filling.
                    </p>
                </div>
            </div>

            <!-- RANGKAIAN: Tampilkan semua proses dalam satu halaman (Mixing + Filling) -->
            <div v-if="isRangkaian" class="mb-6 space-y-4">
                <h3
                    class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300"
                >
                    Rangkaian Proses — Semua tahapan dalam satu halaman
                </h3>
                <div
                    v-for="item in rangkaian"
                    :key="item.id"
                    class="overflow-hidden rounded-lg border bg-white dark:bg-gray-800"
                    :class="
                        item.id === laporan.id
                            ? 'border-indigo-300 ring-1 ring-indigo-200 dark:border-indigo-700 dark:ring-indigo-800'
                            : 'border-gray-200 dark:border-gray-700'
                    "
                >
                    <div
                        class="flex items-center justify-between px-4 py-3"
                        :class="
                            item.proses === 'mixing'
                                ? 'bg-blue-50 dark:bg-blue-900/20'
                                : 'bg-green-50 dark:bg-green-900/20'
                        "
                    >
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-xs font-semibold',
                                    item.proses === 'mixing'
                                        ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200'
                                        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                ]"
                                >{{
                                    prosesLabel[item.proses] ?? item.proses
                                }}</span
                            >
                            <span
                                class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                >{{ item.batch_number }}</span
                            >
                            <span
                                v-if="item.id === laporan.id"
                                class="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200"
                                >Saat ini</span
                            >
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-xs font-medium',
                                    item.status === 'submitted' ||
                                    item.timer_status === 'end'
                                        ? 'bg-green-100 text-green-800'
                                        : item.timer_status === 'start'
                                          ? 'bg-blue-100 text-blue-800'
                                          : item.timer_status === 'pause'
                                            ? 'bg-yellow-100 text-yellow-800'
                                            : 'bg-gray-100 text-gray-600',
                                ]"
                                >{{ laporanTimerLabel(item) }}</span
                            >
                            <span
                                :class="[
                                    'rounded px-2 py-0.5 text-xs',
                                    item.status === 'locked'
                                        ? 'bg-gray-800 text-white'
                                        : 'bg-gray-100 text-gray-600',
                                ]"
                                >{{ item.status }}</span
                            >
                        </div>
                    </div>
                    <div
                        class="grid grid-cols-2 gap-3 p-4 text-sm sm:grid-cols-4"
                    >
                        <div>
                            <dt class="text-xs text-gray-500">Mesin</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.mesin?.nama_mesin ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">CT</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.ct }} mnt
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Line</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.line?.nama_line ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Gross</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.gross_time_menit ?? '-' }} mnt
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Start</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.start_time ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">End</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.end_time ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">Output</dt>
                            <dd
                                class="font-medium text-gray-900 dark:text-gray-100"
                            >
                                {{ item.output_fisik ?? '-' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-gray-500">OEE</dt>
                            <dd
                                class="font-semibold"
                                :class="
                                    item.oee_persen !== null
                                        ? item.oee_persen >= 85
                                            ? 'text-green-600'
                                            : item.oee_persen >= 65
                                              ? 'text-yellow-600'
                                              : 'text-red-600'
                                        : 'text-gray-400'
                                "
                            >
                                {{
                                    item.oee_persen !== null
                                        ? item.oee_persen.toFixed(1) + '%'
                                        : '-'
                                }}
                            </dd>
                        </div>
                    </div>
                    <!-- Timer controls per rangkaian item (only if not finished and is current or sibling) -->
                    <div v-if="!isLaporanFinished(item)" class="px-4 pb-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <PrimaryButton
                                v-if="item.timer_status === 'draft'"
                                @click="
                                    item.id === laporan.id
                                        ? startTimer()
                                        : startTimerFor(item.id)
                                "
                                type="button"
                                size="sm"
                                :disabled="
                                    item.proses === 'filling' &&
                                    !canStartFilling
                                "
                            >
                                ▶ Start {{ prosesLabel[item.proses] }}
                            </PrimaryButton>
                            <PrimaryButton
                                v-if="item.timer_status === 'start'"
                                @click="
                                    item.id === laporan.id
                                        ? pauseTimer()
                                        : pauseTimerFor(item.id)
                                "
                                type="button"
                                size="sm"
                                >⏸ Pause</PrimaryButton
                            >
                            <PrimaryButton
                                v-if="item.timer_status === 'pause'"
                                @click="
                                    item.id === laporan.id
                                        ? resumeTimer()
                                        : resumeTimerFor(item.id)
                                "
                                type="button"
                                size="sm"
                                :disabled="!alasanDowntime.trim()"
                                >▶ Resume</PrimaryButton
                            >
                            <DangerButton
                                v-if="
                                    item.timer_status === 'start' ||
                                    item.timer_status === 'pause'
                                "
                                @click="
                                    item.id === laporan.id
                                        ? endTimer()
                                        : endTimerFor(item.id)
                                "
                                type="button"
                                size="sm"
                                >⏹ End</DangerButton
                            >
                            <Link
                                v-if="item.id !== laporan.id"
                                :href="
                                    route('leader.laporan-harian.show', item.id)
                                "
                                class="text-xs text-indigo-600 hover:underline dark:text-indigo-400"
                                >Buka detail →</Link
                            >
                        </div>
                        <!-- Input Alasan Downtime saat Pause -->
                        <div
                            v-if="item.timer_status === 'pause'"
                            class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20"
                        >
                            <InputLabel value="Alasan Downtime *" />
                            <input
                                v-model="alasanDowntime"
                                type="text"
                                class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="Masukkan alasan downtime..."
                                required
                            />
                            <p
                                class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                            >
                                Alasan wajib diisi sebelum Resume atau End.
                            </p>
                        </div>
                        <p
                            v-if="item.proses === 'filling' && !canStartFilling"
                            class="mt-2 text-xs text-amber-600 dark:text-amber-400"
                        >
                            ⚠️ Mixing harus End terlebih dahulu sebelum Filling
                            dapat dimulai.
                        </p>
                    </div>
                    <div v-else class="px-4 pb-3">
                        <span
                            class="text-xs font-medium text-green-700 dark:text-green-300"
                            >✓ Selesai —
                            {{
                                item.status === 'submitted'
                                    ? 'Terkirim'
                                    : item.status === 'locked'
                                      ? 'Terkunci'
                                      : 'End'
                            }}</span
                        >
                        <Link
                            v-if="item.id !== laporan.id"
                            :href="route('leader.laporan-harian.show', item.id)"
                            class="ml-2 text-xs text-indigo-600 hover:underline"
                            >Lihat detail →</Link
                        >
                    </div>
                </div>
                <!-- Placeholder untuk next process yang belum ada laporan (hanya jika hasNextProcess dan belum auto-created) -->
                <div
                    v-if="hasNextProcess && nextProcessPlan"
                    class="rounded-lg border border-dashed border-green-300 bg-green-50 p-4 dark:border-green-800 dark:bg-green-900/20"
                >
                    <p
                        class="text-sm font-medium text-green-800 dark:text-green-200"
                    >
                        Proses berikutnya:
                        {{ prosesLabel[nextProcessPlan.proses] }} —
                        {{ nextProcessPlan.batch_number }}
                    </p>
                    <p class="text-xs text-green-700 dark:text-green-300">
                        Akan otomatis dibuat setelah
                        {{ prosesLabel[laporan.proses] }} End. Tetap di halaman
                        ini.
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <!-- Info (current laporan) -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                        >
                            Informasi — {{ prosesLabel[laporan.proses] }}
                        </h3>
                        <dl class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Tanggal</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ formatDate(laporan.tanggal) }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Status</dt>
                                <dd class="mt-1">
                                    <span
                                        :class="[
                                            'rounded px-2 py-0.5 text-xs font-medium',
                                            statusColor[laporan.status] ??
                                                'bg-gray-100 text-gray-800',
                                        ]"
                                        >{{ laporan.status }}</span
                                    >
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Produk</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.produk?.nama_produk }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Proses</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        prosesLabel[laporan.proses] ??
                                        laporan.proses
                                    }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Batch Number</dt>
                                <dd
                                    class="mt-1 font-mono font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.batch_number }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Leader</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.user?.name }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Mesin & Waktu (current) -->
                    <div
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <div class="mb-4 flex items-center justify-between">
                            <h3
                                class="text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                            >
                                Mesin & Waktu —
                                {{ prosesLabel[laporan.proses] }}
                            </h3>
                            <span
                                v-if="!isFinished"
                                class="text-xs text-indigo-600 dark:text-indigo-400"
                                >Parameter proses dapat disesuaikan</span
                            >
                        </div>

                        <!-- Form Input saat masih draft / belum selesai -->
                        <div
                            v-if="!isFinished"
                            class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4"
                        >
                            <div>
                                <InputLabel value="Mesin" />
                                <select
                                    v-model="mesinId"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                >
                                    <option
                                        v-for="m in mesins"
                                        :key="m.id"
                                        :value="m.id"
                                    >
                                        {{ m.nama_mesin }} (CT: {{ m.ct }} mnt)
                                    </option>
                                </select>
                            </div>
                            <div>
                                <InputLabel value="CT (Cycle Time)" />
                                <div
                                    class="mt-1 flex h-[42px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ ctVal }} mnt
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Line" />
                                <select
                                    v-model="lineId"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
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
                            <div>
                                <InputLabel value="Gross Time" />
                                <div
                                    class="mt-1 flex h-[42px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ showKpi.grossTime }} mnt
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Target per Output" />
                                <input
                                    v-model="targetMp"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="no-spinner mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    placeholder="Target per MP..."
                                />
                            </div>
                            <div>
                                <InputLabel value="Total MP" />
                                <input
                                    v-model="totalMp"
                                    type="number"
                                    min="1"
                                    class="no-spinner mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    placeholder="Jumlah MP..."
                                />
                            </div>
                            <div>
                                <InputLabel value="Capacity Fisik" />
                                <div
                                    class="mt-1 flex h-[42px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{
                                        (
                                            Number(capacityFisik) || 0
                                        ).toLocaleString('id-ID')
                                    }}
                                </div>
                            </div>
                            <div>
                                <InputLabel value="Start Time" />
                                <div
                                    class="mt-1 flex h-[42px] items-center rounded-md border border-gray-200 bg-gray-50 px-3 font-medium text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                >
                                    {{ laporan.start_time || '-' }}
                                </div>
                            </div>
                        </div>

                        <!-- Read-only saat laporan sudah submitted / locked -->
                        <dl
                            v-else
                            class="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4"
                        >
                            <div>
                                <dt class="text-gray-500">Mesin</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.mesin?.nama_mesin }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">CT</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.ct }} mnt
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Line</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.line?.nama_line }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Gross Time</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ showKpi.grossTime }} mnt
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Start Time</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.start_time }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">End Time</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.end_time ?? '-' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Target MP</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.target_mp }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-gray-500">Total MP</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{ laporan.total_mp }}
                                </dd>
                            </div>
                        </dl>

                        <!-- Timer Controls (only when not finished) -->
                        <div
                            v-if="!isFinished"
                            class="mt-6 rounded-lg bg-gray-50 p-4 dark:bg-gray-700"
                        >
                            <div class="flex flex-wrap items-center gap-4">
                                <div
                                    class="font-mono text-3xl font-bold"
                                    :class="
                                        timerRunning
                                            ? 'text-green-600 dark:text-green-400'
                                            : 'text-gray-400'
                                    "
                                >
                                    {{ timerDisplay }}
                                </div>
                                <div class="flex gap-2">
                                    <PrimaryButton
                                        v-if="laporan.timer_status === 'draft'"
                                        @click="startTimer"
                                        type="button"
                                        size="sm"
                                        :disabled="
                                            laporan.proses === 'filling' &&
                                            !canStartFilling
                                        "
                                    >
                                        ▶ Start
                                    </PrimaryButton>
                                    <PrimaryButton
                                        v-if="laporan.timer_status === 'start'"
                                        @click="pauseTimer"
                                        type="button"
                                        size="sm"
                                    >
                                        ⏸ Pause
                                    </PrimaryButton>
                                    <PrimaryButton
                                        v-if="laporan.timer_status === 'pause'"
                                        @click="resumeTimer"
                                        type="button"
                                        size="sm"
                                        :disabled="!alasanDowntime.trim()"
                                    >
                                        ▶ Resume
                                    </PrimaryButton>
                                    <DangerButton
                                        v-if="
                                            laporan.timer_status === 'start' ||
                                            laporan.timer_status === 'pause'
                                        "
                                        @click="endTimer"
                                        type="button"
                                        size="sm"
                                    >
                                        ⏹ End
                                    </DangerButton>
                                </div>
                            </div>

                            <!-- Input Alasan Downtime saat Pause -->
                            <div
                                v-if="laporan.timer_status === 'pause'"
                                class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20"
                            >
                                <InputLabel value="Alasan Downtime *" />
                                <input
                                    v-model="alasanDowntime"
                                    type="text"
                                    class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    placeholder="Masukkan alasan downtime..."
                                    required
                                />
                                <p
                                    class="mt-1 text-xs text-amber-600 dark:text-amber-400"
                                >
                                    Alasan wajib diisi sebelum Resume atau End.
                                </p>
                            </div>

                            <div class="mt-2 flex justify-between text-sm">
                                <span class="text-gray-500"
                                    >Status:
                                    <span class="font-semibold capitalize">{{
                                        laporan.timer_status === 'start'
                                            ? 'Berjalan'
                                            : laporan.timer_status === 'pause'
                                              ? 'Dijeda'
                                              : laporan.timer_status
                                    }}</span></span
                                >
                                <span
                                    v-if="laporan.timer_status === 'pause'"
                                    class="font-semibold text-amber-600 dark:text-amber-400"
                                >
                                    Pause:
                                    <span class="font-mono">{{
                                        downtimeDisplay
                                    }}</span>
                                </span>
                                <span
                                    v-else-if="laporan.total_pause_menit > 0"
                                    class="text-gray-500"
                                    >Total Pause:
                                    <span class="font-medium"
                                        >{{
                                            laporan.total_pause_menit
                                        }}
                                        mnt</span
                                    ></span
                                >
                            </div>
                            <p
                                v-if="
                                    laporan.proses === 'filling' &&
                                    !canStartFilling
                                "
                                class="mt-2 text-xs text-amber-600"
                            >
                                ⚠️ Mixing harus End terlebih dahulu.
                            </p>
                        </div>
                        <div
                            v-else
                            class="mt-6 rounded-lg bg-green-50 p-4 dark:bg-green-900/20"
                        >
                            <p
                                class="text-sm font-medium text-green-800 dark:text-green-200"
                            >
                                Timer selesai — Laporan terkirim
                            </p>
                        </div>
                    </div>

                    <!-- Output Fisik — show after End, before Submit -->
                    <div
                        v-if="laporan.timer_status === 'end' || isFinished"
                        class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                        >
                            Output — {{ prosesLabel[laporan.proses] }}
                        </h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <dt class="text-gray-500">Capacity Fisik</dt>
                                <dd
                                    class="mt-1 font-medium text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        (
                                            Number(capacityFisik) ||
                                            laporan.capacity_fisik
                                        )?.toLocaleString('id-ID')
                                    }}
                                </dd>
                            </div>
                            <div>
                                <InputLabel value="Output Fisik" />
                                <input
                                    v-model="outputFisik"
                                    type="number"
                                    min="0"
                                    class="no-spinner mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    :disabled="isFinished"
                                    :class="{
                                        'cursor-not-allowed opacity-50':
                                            isFinished,
                                    }"
                                    placeholder="Masukkan output fisik..."
                                />
                                <p
                                    v-if="isFinished"
                                    class="mt-1 text-xs text-gray-500"
                                >
                                    Output sudah terkunci
                                </p>
                            </div>
                        </div>
                        <div
                            v-if="
                                laporan.timer_status === 'end' &&
                                laporan.status !== 'submitted' &&
                                laporan.status !== 'locked'
                            "
                            class="mt-4"
                        >
                            <PrimaryButton
                                @click="submitLaporan"
                                :disabled="
                                    !outputFisik || Number(outputFisik) <= 0
                                "
                            >
                                Kirim Laporan
                            </PrimaryButton>
                            <p class="mt-1 text-xs text-gray-500">
                                Isi Output Fisik terlebih dahulu, lalu klik
                                Kirim.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- KPI Sidebar (current) -->
                <div class="lg:col-span-1">
                    <div
                        class="sticky top-6 rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
                    >
                        <h3
                            class="mb-4 text-sm font-semibold uppercase text-gray-500 dark:text-gray-400"
                        >
                            KPI — {{ prosesLabel[laporan.proses] }}
                        </h3>
                        <div class="space-y-4">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Waktu Bersih</span>
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100"
                                    >{{ showKpi.waktuBersih }} mnt</span
                                >
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600"
                                    >Target Teoritis</span
                                >
                                <span
                                    class="font-medium text-gray-900 dark:text-gray-100"
                                    >{{
                                        showKpi.targetTeoritis.toFixed(0)
                                    }}</span
                                >
                            </div>
                            <hr class="dark:border-gray-700" />
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Quality</span>
                                <span
                                    class="font-semibold"
                                    :class="kpiColor(showKpi.quality)"
                                    >{{ showKpi.quality.toFixed(1) }}%</span
                                >
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Availability</span>
                                <span
                                    class="font-semibold"
                                    :class="kpiColor(showKpi.availability)"
                                    >{{
                                        showKpi.availability.toFixed(1)
                                    }}%</span
                                >
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Performance</span>
                                <span
                                    class="font-semibold"
                                    :class="kpiColor(showKpi.performance)"
                                    >{{ showKpi.performance.toFixed(1) }}%</span
                                >
                            </div>
                            <hr class="dark:border-gray-700" />
                            <div class="flex justify-between text-lg font-bold">
                                <span class="text-gray-600">OEE</span>
                                <span :class="kpiColor(showKpi.oee)"
                                    >{{ showKpi.oee.toFixed(1) }}%</span
                                >
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Produktivitas</span>
                                <span
                                    class="font-semibold"
                                    :class="kpiColor(showKpi.produktivitas)"
                                    >{{
                                        showKpi.produktivitas.toFixed(1)
                                    }}%</span
                                >
                            </div>
                        </div>
                        <div
                            v-if="isRangkaian"
                            class="mt-6 border-t border-gray-200 pt-4 dark:border-gray-700"
                        >
                            <h4
                                class="mb-2 text-xs font-semibold text-gray-500 dark:text-gray-400"
                            >
                                Rangkaian KPI
                            </h4>
                            <div class="space-y-2">
                                <div
                                    v-for="item in rangkaian"
                                    :key="item.id"
                                    class="flex justify-between text-xs"
                                >
                                    <span class="text-gray-600">{{
                                        prosesLabel[item.proses]
                                    }}</span>
                                    <span
                                        class="font-medium"
                                        :class="
                                            item.oee_persen !== null
                                                ? kpiColor(item.oee_persen)
                                                : 'text-gray-400'
                                        "
                                        >{{
                                            item.oee_persen !== null
                                                ? item.oee_persen.toFixed(1) +
                                                  '%'
                                                : '-'
                                        }}</span
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
