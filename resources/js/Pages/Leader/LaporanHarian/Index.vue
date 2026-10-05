<script setup lang="ts">
import Pagination from '@/Components/Pagination.vue';
import SearchInput from '@/Components/SearchInput.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatDate } from '@/utils/date';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Produk {
    id: number;
    nama_produk: string;
}
interface Mesin {
    id: number;
    nama_mesin: string;
}
interface Line {
    id: number;
    nama_line: string;
}

interface Laporan {
    id: number;
    tanggal: string;
    shift?: string | null;
    batch_number: string;
    proses: string;
    oee_persen: number | null;
    output_fisik: number | null;
    status: string;
    timer_status: string;
    produk: Produk;
    mesin: Mesin;
    line: Line;
    user?: { id: number; name: string };
}

const props = defineProps<{
    laporans: {
        data: Laporan[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    search?: string;
    shift?: string | null;
}>();

const isManager = computed(
    () => (usePage().props as any).auth?.user?.role === 'manager',
);

const search = ref(props.search ?? '');
const shift = ref(props.shift ?? '');

function goToPage(page: number) {
    router.get(
        route('leader.laporan-harian.index'),
        { page, search: search.value, shift: shift.value || undefined },
        { preserveState: true },
    );
}

function applyShift() {
    router.get(
        route('leader.laporan-harian.index'),
        { search: search.value || undefined, shift: shift.value || undefined },
        { preserveState: true },
    );
}

const shiftLabel: Record<string, string> = {
    shift1: 'Shift 1',
    shift2: 'Shift 2',
};

const statusColor: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    submitted:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-200',
    locked: 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-200',
};

const timerColor: Record<string, string> = {
    start: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 ring-1 ring-emerald-500/30 font-semibold',
    pause: 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 ring-1 ring-amber-500/30 font-semibold',
    end: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
    draft: 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400',
};

const timerLabel: Record<string, string> = {
    start: '● Berjalan',
    pause: '❚❚ Dijeda',
    end: 'Selesai',
    draft: 'Draft',
};

const prosesLabel: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    packing: 'Packing',
    packaging: 'Packaging',
};

function oeeColor(v: number | null): string {
    if (v === null) return 'text-gray-400';
    if (v >= 85) return 'text-green-600 dark:text-green-400';
    if (v >= 65) return 'text-yellow-600 dark:text-yellow-400';
    return 'text-red-600 dark:text-red-400';
}
</script>

<template>
    <Head title="Laporan Harian" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2
                        class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-200"
                    >
                        Laporan Harian
                    </h2>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                        Pencatatan produksi harian, tracking timer proses &
                        perhitungan OEE
                    </p>
                </div>
                <Link
                    v-if="!isManager"
                    :href="route('leader.laporan-harian.create')"
                    class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:bg-indigo-500 dark:hover:bg-indigo-600"
                >
                    <svg
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="2"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 4.5v15m7.5-7.5h-15"
                        />
                    </svg>
                    <span>Tambah Laporan</span>
                </Link>
                <span
                    v-else
                    class="inline-flex items-center rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >Read-only</span
                >
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div
                class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <SearchInput
                        v-model="search"
                        placeholder="Cari batch, produk..."
                        class="w-full max-w-md"
                        @search="goToPage(1)"
                    />
                    <select
                        v-model="shift"
                        class="rounded-md border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        @change="applyShift"
                    >
                        <option value="">Semua Shift</option>
                        <option value="shift1">Shift 1</option>
                        <option value="shift2">Shift 2</option>
                    </select>
                </div>
                <span class="text-xs text-gray-500 dark:text-gray-400"
                    >Total: <strong>{{ laporans.total }}</strong> Laporan</span
                >
            </div>

            <!-- DESKTOP TABLE VIEW (md and up) -->
            <div
                class="hidden overflow-hidden bg-white shadow-sm dark:bg-gray-800 sm:rounded-lg md:block"
            >
                <div class="overflow-x-auto">
                    <table
                        class="w-full divide-y divide-gray-200 text-xs dark:divide-gray-700 sm:text-sm min-w-[750px]"
                    >
                        <thead class="bg-gray-50 dark:bg-gray-700/70">
                            <tr>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Tanggal
                                </th>
                                <th
                                    class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Produk & Batch
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Proses
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Line & Mesin
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Output
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    OEE
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Status & Timer
                                </th>
                                <th
                                    class="whitespace-nowrap px-3 py-2.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-300"
                                >
                                    Aksi
                                </th>
                            </tr>
                        </thead>
                        <tbody
                            class="divide-y divide-gray-200 bg-white dark:divide-gray-700 dark:bg-gray-800"
                        >
                            <tr v-if="laporans.data.length === 0">
                                <td
                                    colspan="8"
                                    class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400"
                                >
                                    Tidak ada data laporan
                                </td>
                            </tr>
                            <tr
                                v-for="item in laporans.data"
                                :key="item.id"
                                class="transition hover:bg-gray-50/80 dark:hover:bg-gray-700/60"
                            >
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <div
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ formatDate(item.tanggal) }}
                                    </div>
                                    <div
                                        v-if="item.shift"
                                        class="text-[11px] text-gray-500 dark:text-gray-400"
                                    >
                                        {{
                                            shiftLabel[item.shift] ?? item.shift
                                        }}
                                        · {{ item.user?.name ?? 'Leader' }}
                                    </div>
                                    <div
                                        v-else
                                        class="text-[11px] text-gray-500 dark:text-gray-400"
                                    >
                                        oleh {{ item.user?.name ?? 'Leader' }}
                                    </div>
                                </td>
                                <td class="px-3 py-2.5">
                                    <div
                                        class="font-semibold text-gray-900 dark:text-gray-100"
                                    >
                                        {{ item.produk?.nama_produk ?? '-' }}
                                    </div>
                                    <div
                                        class="mt-0.5 inline-flex items-center gap-1 font-mono text-[11px] font-bold text-gray-700 dark:text-gray-300"
                                    >
                                        <span
                                            class="rounded bg-gray-100 px-1.5 py-0.5 dark:bg-gray-700"
                                            >Batch:
                                            {{ item.batch_number }}</span
                                        >
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <span
                                        class="inline-flex rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-700 dark:bg-slate-700 dark:text-slate-200"
                                    >
                                        {{
                                            prosesLabel[item.proses] ??
                                            item.proses
                                        }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-2.5">
                                    <div
                                        class="font-medium text-gray-900 dark:text-gray-100"
                                    >
                                        {{ item.line?.nama_line ?? '-' }}
                                    </div>
                                    <div
                                        class="text-[11px] text-gray-500 dark:text-gray-400"
                                    >
                                        {{ item.mesin?.nama_mesin ?? '-' }}
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-2.5 text-right font-bold text-gray-900 dark:text-gray-100"
                                >
                                    {{
                                        item.output_fisik !== null
                                            ? item.output_fisik.toLocaleString(
                                                  'id-ID',
                                              )
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-2.5 text-right font-bold"
                                    :class="oeeColor(item.oee_persen)"
                                >
                                    {{
                                        item.oee_persen !== null
                                            ? item.oee_persen.toFixed(1) + '%'
                                            : '-'
                                    }}
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-2.5 text-center"
                                >
                                    <div
                                        class="flex flex-col items-center gap-1"
                                    >
                                        <span
                                            :class="[
                                                'inline-flex rounded-full px-2 py-0.5 text-[11px]',
                                                timerColor[item.timer_status] ??
                                                    'bg-gray-100 text-gray-500',
                                            ]"
                                        >
                                            {{
                                                timerLabel[item.timer_status] ??
                                                item.timer_status
                                            }}
                                        </span>
                                        <span
                                            :class="[
                                                'inline-flex rounded px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider',
                                                statusColor[item.status] ??
                                                    'bg-gray-100 text-gray-800',
                                            ]"
                                        >
                                            {{ item.status }}
                                        </span>
                                    </div>
                                </td>
                                <td
                                    class="whitespace-nowrap px-3 py-2.5 text-right"
                                >
                                    <Link
                                        :href="
                                            route(
                                                'leader.laporan-harian.show',
                                                item.id,
                                            )
                                        "
                                        class="inline-flex items-center gap-1 rounded-md bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60"
                                    >
                                        <span>Detail</span>
                                        <svg
                                            class="h-3.5 w-3.5"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                stroke-width="2"
                                                d="M9 5l7 7-7 7"
                                            />
                                        </svg>
                                    </Link>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div
                    class="border-t border-gray-200 bg-white px-6 py-3 dark:border-gray-700 dark:bg-gray-800"
                >
                    <Pagination
                        :current-page="laporans.current_page"
                        :last-page="laporans.last_page"
                        :total="laporans.total"
                        :per-page="laporans.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>

            <!-- MOBILE CARD VIEW (md:hidden) -->
            <div class="block space-y-3 md:hidden">
                <div
                    v-if="laporans.data.length === 0"
                    class="rounded-xl border border-gray-200 bg-white p-8 text-center text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-800"
                >
                    Tidak ada data laporan produksi.
                </div>

                <div
                    v-else
                    v-for="item in laporans.data"
                    :key="item.id"
                    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition dark:border-gray-700 dark:bg-gray-800"
                >
                    <div
                        class="flex items-start justify-between gap-2 border-b border-gray-100 pb-3 dark:border-gray-700"
                    >
                        <div>
                            <span
                                class="rounded bg-indigo-50 px-2 py-0.5 font-mono text-xs font-bold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300"
                            >
                                {{ item.batch_number }}
                            </span>
                            <h3
                                class="mt-1 text-sm font-bold text-gray-900 dark:text-gray-100"
                            >
                                {{ item.produk?.nama_produk ?? '-' }}
                            </h3>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span
                                :class="[
                                    'inline-flex rounded-full px-2 py-0.5 text-[10px]',
                                    timerColor[item.timer_status] ??
                                        'bg-gray-100 text-gray-500',
                                ]"
                            >
                                {{
                                    timerLabel[item.timer_status] ??
                                    item.timer_status
                                }}
                            </span>
                            <span
                                :class="[
                                    'inline-flex rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wider',
                                    statusColor[item.status] ??
                                        'bg-gray-100 text-gray-800',
                                ]"
                            >
                                {{ item.status }}
                            </span>
                        </div>
                    </div>

                    <div class="my-3 grid grid-cols-2 gap-2 text-xs">
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Proses & Line</span
                            >
                            <span
                                class="font-medium text-gray-800 dark:text-gray-200"
                                >{{ prosesLabel[item.proses] ?? item.proses }} ·
                                {{ item.line?.nama_line ?? '-' }}</span
                            >
                        </div>
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Mesin</span
                            >
                            <span
                                class="font-medium text-gray-800 dark:text-gray-200"
                                >{{ item.mesin?.nama_mesin ?? '-' }}</span
                            >
                        </div>
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Output Fisik</span
                            >
                            <span
                                class="font-bold text-gray-900 dark:text-gray-100"
                                >{{
                                    item.output_fisik !== null
                                        ? item.output_fisik.toLocaleString(
                                              'id-ID',
                                          )
                                        : '-'
                                }}
                                pcs</span
                            >
                        </div>
                        <div
                            class="rounded-lg bg-gray-50 p-2 dark:bg-gray-900/40"
                        >
                            <span
                                class="block text-[10px] text-gray-500 dark:text-gray-400"
                                >Nilai OEE</span
                            >
                            <span
                                class="font-bold"
                                :class="oeeColor(item.oee_persen)"
                                >{{
                                    item.oee_persen !== null
                                        ? item.oee_persen.toFixed(1) + '%'
                                        : '-'
                                }}</span
                            >
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between border-t border-gray-100 pt-2.5 text-xs text-gray-500 dark:border-gray-700 dark:text-gray-400"
                    >
                        <span
                            >📅 {{ formatDate(item.tanggal) }} ({{
                                item.user?.name ?? 'Leader'
                            }})</span
                        >
                        <Link
                            :href="route('leader.laporan-harian.show', item.id)"
                            class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-1 text-xs font-semibold text-white shadow-sm transition hover:bg-indigo-700 active:scale-95 dark:bg-indigo-500"
                        >
                            <span>Lihat Detail</span>
                            <svg
                                class="h-3.5 w-3.5"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5l7 7-7 7"
                                />
                            </svg>
                        </Link>
                    </div>
                </div>

                <!-- Mobile Pagination -->
                <div
                    class="mt-3 border-t border-gray-200 bg-white px-4 py-3 dark:border-gray-700 dark:bg-gray-800 sm:rounded-lg"
                >
                    <Pagination
                        :current-page="laporans.current_page"
                        :last-page="laporans.last_page"
                        :total="laporans.total"
                        :per-page="laporans.per_page"
                        @page="goToPage"
                    />
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
