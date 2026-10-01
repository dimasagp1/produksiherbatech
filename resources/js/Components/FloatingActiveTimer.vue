<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

interface ActiveProduction {
    id: number;
    batch_number: string;
    proses: string;
    tanggal: string;
    timer_status: 'start' | 'pause';
    start_time: string | null;
    start_time_at: string | null;
    total_pause_menit: number;
    pause_started_at: string | null;
    produk: {
        id: number;
        nama_produk: string;
    } | null;
    line: {
        id: number;
        nama_line: string;
    } | null;
}

const page = usePage();
const activeProduction = computed<ActiveProduction | null>(
    () => (page.props as any).active_production ?? null,
);

const isMinimized = ref(false);
const timerElapsed = ref(0);
let timerInterval: number | null = null;

// Check if user is already looking at this specific Laporan Harian detail page
const isCurrentlyOnDetailPage = computed(() => {
    try {
        if (!activeProduction.value) return false;
        const currentUrl = page.url;
        return currentUrl.includes(
            `/leader/laporan-harian/${activeProduction.value.id}`,
        );
    } catch {
        return false;
    }
});

const userRole = computed(() => (page.props as any).auth?.user?.role ?? null);

const shouldShow = computed(() => {
    if (userRole.value === 'ppic') return false;
    return !!activeProduction.value && !isCurrentlyOnDetailPage.value;
});

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
    packaging: 'Packaging',
};

function parseTimeToSeconds(t: string): number {
    const parts = t.split(':').map(Number);
    const h = parts[0] ?? 0,
        m = parts[1] ?? 0,
        s = parts[2] ?? 0;
    return h * 3600 + m * 60 + s;
}

function updateElapsedTime() {
    if (!activeProduction.value || !activeProduction.value.start_time) {
        timerElapsed.value = 0;
        return;
    }

    const status = activeProduction.value.timer_status;
    const totalPauseSeconds =
        (activeProduction.value.total_pause_menit || 0) * 60;

    if (activeProduction.value.start_time_at) {
        const startAt = new Date(activeProduction.value.start_time_at);
        if (status === 'start') {
            const now = new Date();
            const diffSec = Math.floor(
                (now.getTime() - startAt.getTime()) / 1000,
            );
            timerElapsed.value = Math.max(0, diffSec - totalPauseSeconds);
        } else if (status === 'pause') {
            const pauseAt = activeProduction.value.pause_started_at
                ? new Date(activeProduction.value.pause_started_at)
                : new Date();
            const diffSec = Math.floor(
                (pauseAt.getTime() - startAt.getTime()) / 1000,
            );
            timerElapsed.value = Math.max(0, diffSec - totalPauseSeconds);
        }
    } else {
        const startSeconds = parseTimeToSeconds(
            activeProduction.value.start_time,
        );
        if (status === 'start') {
            const now = new Date();
            const nowSeconds =
                now.getHours() * 3600 +
                now.getMinutes() * 60 +
                now.getSeconds();
            let diff = nowSeconds - startSeconds;
            if (diff < 0) diff += 24 * 3600;
            timerElapsed.value = Math.max(0, diff - totalPauseSeconds);
        } else if (status === 'pause') {
            const pauseAt = activeProduction.value.pause_started_at
                ? new Date(activeProduction.value.pause_started_at)
                : new Date();
            const pauseSecondsAt =
                pauseAt.getHours() * 3600 +
                pauseAt.getMinutes() * 60 +
                pauseAt.getSeconds();
            let diff = pauseSecondsAt - startSeconds;
            if (diff < 0) diff += 24 * 3600;
            timerElapsed.value = Math.max(0, diff - totalPauseSeconds);
        }
    }
}

function startTimerLoop() {
    stopTimerLoop();
    updateElapsedTime();
    if (activeProduction.value?.timer_status === 'start') {
        timerInterval = window.setInterval(() => {
            timerElapsed.value++;
        }, 1000);
    }
}

function stopTimerLoop() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

const formattedTimer = computed(() => {
    const totalSecs = timerElapsed.value;
    const h = Math.floor(totalSecs / 3600);
    const m = Math.floor((totalSecs % 3600) / 60);
    const s = totalSecs % 60;
    return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
});

watch(
    activeProduction,
    (prod) => {
        if (prod && prod.timer_status === 'start') {
            startTimerLoop();
        } else {
            stopTimerLoop();
            updateElapsedTime();
        }
    },
    { immediate: true, deep: true },
);

onMounted(() => {
    if (activeProduction.value) {
        startTimerLoop();
    }
});

onUnmounted(() => {
    stopTimerLoop();
});
</script>

<template>
    <div
        v-if="shouldShow && activeProduction"
        class="fixed bottom-5 right-5 z-50 transition-all duration-300 ease-out"
        :class="{ 'translate-y-0 opacity-100': shouldShow }"
    >
        <!-- MINIMIZED VIEW (SLEEK GLOWING PILL) -->
        <div
            v-if="isMinimized"
            @click="isMinimized = false"
            class="group flex cursor-pointer items-center gap-2.5 rounded-full border border-emerald-500/40 bg-slate-900/90 px-3.5 py-2 shadow-xl backdrop-blur-md transition-all hover:scale-105 hover:border-emerald-400 dark:bg-[#12151F]/95"
            title="Klik untuk memperbesar widget produksi aktif"
        >
            <span class="relative flex h-3 w-3">
                <span
                    v-if="activeProduction.timer_status === 'start'"
                    class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                ></span>
                <span
                    class="relative inline-flex h-3 w-3 rounded-full"
                    :class="
                        activeProduction.timer_status === 'start'
                            ? 'bg-emerald-500'
                            : 'bg-amber-500'
                    "
                ></span>
            </span>

            <div
                class="flex items-center gap-1.5 font-mono text-xs font-bold text-white"
            >
                <span>{{ activeProduction.batch_number }}</span>
                <span class="text-emerald-400">⏱️ {{ formattedTimer }}</span>
            </div>

            <button
                type="button"
                @click.stop="isMinimized = false"
                class="ml-1 rounded-full p-0.5 text-gray-400 hover:text-white"
            >
                <svg
                    class="h-3.5 w-3.5"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2.5"
                        d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"
                    />
                </svg>
            </button>
        </div>

        <!-- EXPANDED VIEW (RICH FLOATING DOCK) -->
        <div
            v-else
            class="w-80 rounded-2xl border border-emerald-500/30 bg-white/95 p-4 shadow-2xl backdrop-blur-md transition-all dark:border-emerald-500/30 dark:bg-[#12151F]/95 dark:text-white"
        >
            <!-- Header Bar -->
            <div
                class="flex items-center justify-between border-b border-gray-100 pb-2.5 dark:border-gray-800"
            >
                <div class="flex items-center gap-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span
                            v-if="activeProduction.timer_status === 'start'"
                            class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"
                        ></span>
                        <span
                            class="relative inline-flex h-2.5 w-2.5 rounded-full"
                            :class="
                                activeProduction.timer_status === 'start'
                                    ? 'bg-emerald-500'
                                    : 'bg-amber-500'
                            "
                        ></span>
                    </span>
                    <span
                        class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
                    >
                        {{
                            activeProduction.timer_status === 'start'
                                ? 'PROSES SEDANG BERJALAN'
                                : 'PROSES DIJEDA (PAUSE)'
                        }}
                    </span>
                </div>

                <div class="flex items-center gap-1">
                    <button
                        type="button"
                        @click="isMinimized = true"
                        class="rounded-md p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-700 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                        title="Perkecil widget"
                    >
                        <svg
                            class="h-3.5 w-3.5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2.5"
                                d="M19 9l-7 7-7-7"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="mt-3 space-y-2.5">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center gap-1.5">
                            <span
                                class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs font-bold text-gray-900 dark:bg-gray-800 dark:text-gray-100"
                            >
                                {{ activeProduction.batch_number }}
                            </span>
                            <span
                                class="rounded bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300"
                            >
                                {{
                                    prosesLabel[activeProduction.proses] ??
                                    activeProduction.proses
                                }}
                            </span>
                        </div>
                        <p
                            class="mt-1 line-clamp-1 text-xs font-semibold text-gray-800 dark:text-gray-200"
                        >
                            {{
                                activeProduction.produk?.nama_produk ??
                                'Produk Produksi'
                            }}
                        </p>
                    </div>

                    <!-- Timer Badge -->
                    <div class="text-right">
                        <div
                            class="font-mono text-lg font-extrabold tracking-tight"
                            :class="
                                activeProduction.timer_status === 'start'
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-amber-500'
                            "
                        >
                            {{ formattedTimer }}
                        </div>
                        <span class="text-[10px] text-gray-400"
                            >Mulai:
                            {{ activeProduction.start_time ?? '-' }}</span
                        >
                    </div>
                </div>

                <!-- Action Button to Navigate to the Laporan Harian -->
                <Link
                    :href="
                        route('leader.laporan-harian.show', activeProduction.id)
                    "
                    class="group flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 px-4 py-2 text-xs font-bold text-white shadow-md shadow-emerald-600/20 transition-all hover:from-emerald-500 hover:to-teal-500 hover:shadow-lg hover:shadow-emerald-600/30 active:scale-[0.98]"
                >
                    <span>Buka & Kelola Laporan</span>
                    <svg
                        class="h-3.5 w-3.5 transition-transform group-hover:translate-x-0.5"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2.5"
                            d="M14 5l7 7m0 0l-7 7m7-7H3"
                        />
                    </svg>
                </Link>
            </div>
        </div>
    </div>
</template>
