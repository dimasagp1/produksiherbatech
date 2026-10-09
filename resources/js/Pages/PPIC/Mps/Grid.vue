<script setup lang="ts">
import { computed, ref } from 'vue';
import CellModal from './CellModal.vue';
import CellContent from './CellContent.vue';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: string;
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
}

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
    default_work_center_id: number | null;
    effective_ct_seconds: number | null;
    can_run_work_centers: string[] | null;
}

interface MpsItem {
    id: number;
    tanggal: string;
    line_id: number | null;
    work_center_id: number | null;
    shift: string;
    mp_count: number;
    target_qty: number;
    cleaning: boolean;
    adjusted_qty: number | null;
    gap_reason: string | null;
    notes: string | null;
    line: Line | null;
    workCenter: WorkCenter | null;
}

interface MpsPlan {
    id: number;
    produk_id: number;
    work_center_id: number;
    month_year: string;
    status: string;
    items: MpsItem[];
    produk: {
        id: number;
        kode_produk: string;
        nama_produk: string;
    };
    workCenter: {
        id: number;
        code: string;
        name: string;
        type: string;
        standard_ct_seconds: number;
        fit_mp: number;
        shift_hours: number;
        saturday_shift_hours: number;
    };
}

const props = defineProps<{
    plan: MpsPlan;
    lines: Line[];
    workCenters: WorkCenter[];
    daysInMonth: number;
    readOnly?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update-item', item: MpsItem, data: Record<string, any>): void;
    (e: 'close'): void;
}>();

const selectedItem = ref<MpsItem | null>(null);
const showCellModal = ref(false);

const days = computed(() => {
    const arr = [];
    for (let i = 1; i <= props.daysInMonth; i++) {
        arr.push(i);
    }
    return arr;
});

const itemsByDateLineShift = computed(() => {
    const map: Record<string, MpsItem> = {};
    for (const item of props.plan.items) {
        const key = `${String(item.tanggal).slice(0, 10)}-${item.line_id}-${item.shift}`;
        map[key] = item;
    }
    return map;
});

function getItem(day: number, line: Line, shift: string): MpsItem | null {
    const dateStr = `${props.plan.month_year}-${String(day).padStart(2, '0')}`;
    return itemsByDateLineShift.value[`${dateStr}-${line.id}-${shift}`] ?? null;
}

function openCellModal(day: number, line: Line, shift: string) {
    if (props.readOnly) return;
    const item = getItem(day, line, shift);
    selectedItem.value = item ?? {
        id: 0,
        tanggal: `${props.plan.month_year}-${String(day).padStart(2, '0')}`,
        line_id: line.id,
        work_center_id: line.default_work_center_id,
        shift: shift,
        mp_count: 0,
        target_qty: 0,
        cleaning: false,
        adjusted_qty: null,
        gap_reason: null,
        notes: '',
        line: line,
        workCenter: line.default_work_center_id ? props.workCenters.find(wc => wc.id === line.default_work_center_id) ?? null : null,
    } as MpsItem;
    showCellModal.value = true;
}

function handleUpdateItem(item: MpsItem, data: Record<string, any>) {
    emit('update-item', item, data);
}

const typeColor: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    filling: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    secondary: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

const typeIcon: Record<string, string> = {
    mixing: '🥣',
    filling: '🥤',
    secondary: '📦',
};

const shiftLabel: Record<string, string> = {
    shift1: 'S1',
    shift2: 'S2',
};

const shiftColor: Record<string, string> = {
    shift1: 'bg-blue-50 dark:bg-blue-900/30',
    shift2: 'bg-amber-50 dark:bg-amber-900/30',
};

function getDayName(day: number): string {
    const date = new Date(`${props.plan.month_year}-${String(day).padStart(2, '0')}`);
    return ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][date.getDay()];
}

function isSaturday(day: number): boolean {
    const date = new Date(`${props.plan.month_year}-${String(day).padStart(2, '0')}`);
    return date.getDay() === 6;
}

function weekIndex(day: number): number {
    if (day <= 7) return 0;
    if (day <= 14) return 1;
    if (day <= 21) return 2;
    if (day <= 28) return 3;
    return 4;
}

function rowWeeklyTotals(line: Line, shift: string): number[] {
    const w = [0, 0, 0, 0, 0];
    for (let d = 1; d <= props.daysInMonth; d++) {
        const it = getItem(d, line, shift);
        if (it && !it.cleaning) w[weekIndex(d)] += it.target_qty;
    }
    return w;
}

function rowTotal(line: Line, shift: string): number {
    return rowWeeklyTotals(line, shift).reduce((a, b) => a + b, 0);
}

function rowAdjTotal(line: Line, shift: string): number {
    let s = 0;
    for (let d = 1; d <= props.daysInMonth; d++) {
        const it = getItem(d, line, shift);
        if (it && !it.cleaning) s += it.adjusted_qty ?? it.target_qty;
        else if (it && it.cleaning) s += 0;
    }
    return s;
}

function colDayTotal(day: number): number {
    let s = 0;
    for (const line of props.lines) for (const sh of ['shift1', 'shift2'] as const) {
        const it = getItem(day, line, sh);
        if (it && !it.cleaning) s += it.target_qty;
    }
    return s;
}

function colWeekTotal(wIdx: number): number {
    const start = wIdx * 7 + 1;
    const end = wIdx === 4 ? props.daysInMonth : (wIdx + 1) * 7;
    let s = 0;
    for (let d = start; d <= end; d++) s += colDayTotal(d);
    return s;
}

const grandPlan = computed(() => props.lines.reduce((a, l) => a + rowTotal(l, 'shift1') + rowTotal(l, 'shift2'), 0));
const grandAdj = computed(() => props.lines.reduce((a, l) => a + rowAdjTotal(l, 'shift1') + rowAdjTotal(l, 'shift2'), 0));
</script>

<template>
    <div class="space-y-4">
        <!-- Header with Month Navigation -->
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100">
                Grid MPS: {{ props.plan.produk?.nama_produk }} - {{ props.plan.workCenter?.name }}
            </h3>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Shift 1 = Pagi | Shift 2 = Siang | 🧹 = Cleaning (Full Day) | Gap = Adjusted - Target
                </span>
            </div>
        </div>

        <!-- Grid Table -->
        <div class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <table class="w-full text-xs">
                <thead class="bg-gray-50 sticky top-0 z-10 dark:bg-gray-900/80">
                    <tr>
                        <th class="w-24 px-2 py-2 text-left font-semibold text-gray-600 dark:text-gray-400 sticky left-0 z-20 bg-white dark:bg-gray-800">Line</th>
                        <th class="w-16 min-w-[72px] px-1 py-2 text-center font-semibold text-gray-600 dark:text-gray-400" v-for="day in days" :key="day">
                            <div class="text-[10px] font-bold">{{ day }}</div>
                            <div class="text-[9px] text-gray-400">{{ ['Min','Sen','Sel','Rab','Kam','Jum','Sab'][new Date(props.plan.month_year + '-' + String(day).padStart(2,'0')).getDay()] }}</div>
                        </th>
                        <th class="min-w-[56px] px-1 py-2 text-center font-bold text-gray-700 dark:text-gray-300 bg-indigo-50 dark:bg-indigo-950/40" v-for="w in 5" :key="'w'+w">W{{ w }}</th>
                        <th class="min-w-[64px] px-1 py-2 text-center font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-100 dark:bg-indigo-900/40">TOTAL</th>
                        <th class="min-w-[64px] px-1 py-2 text-center font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40">ADJ</th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="line in lines" :key="line.id">
                        <tr v-for="shift in (['shift1','shift2'] as const)" :key="line.id+'-'+shift" class="border-t border-gray-100 dark:border-gray-700">
                            <td class="w-24 px-2 py-1.5 sticky left-0 z-10 bg-white dark:bg-gray-800">
                                <div class="font-mono text-xs font-semibold text-gray-900 dark:text-gray-100">{{ line.nama_line }} <span class="text-[9px] font-normal text-gray-400">{{ shift==='shift1'?'S1':'S2' }}</span></div>
                                <div class="text-[9px] text-gray-400">{{ line.kode_line }}</div>
                            </td>
                            <td v-for="day in days" :key="day" class="px-1 py-1">
                                <CellContent :day="day" :line="line" :shift="shift" :item="getItem(day, line, shift)" :workCenters="workCenters" @click="openCellModal" />
                            </td>
                            <td v-for="wIdx in 5" :key="'w'+wIdx" class="px-1 py-1 text-center font-mono text-[11px] font-bold bg-gray-50 dark:bg-gray-900/40">{{ rowWeeklyTotals(line, shift)[wIdx-1] ? rowWeeklyTotals(line, shift)[wIdx-1].toLocaleString('id-ID') : '-' }}</td>
                            <td class="px-1 py-1 text-center font-mono text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/30">{{ rowTotal(line, shift) ? rowTotal(line, shift).toLocaleString('id-ID') : '-' }}</td>
                            <td class="px-1 py-1 text-center font-mono text-[11px] font-bold bg-emerald-50 dark:bg-emerald-950/30">{{ rowAdjTotal(line, shift) ? rowAdjTotal(line, shift).toLocaleString('id-ID') : '-' }}</td>
                        </tr>
                    </template>
                    <tr class="border-t-2 border-gray-300 bg-gray-100 dark:bg-gray-900 font-bold">
                        <td class="w-24 px-2 py-1.5 sticky left-0 z-10 bg-gray-100 dark:bg-gray-900 text-center text-[10px]" colspan="1">TOTAL</td>
                        <td v-for="day in days" :key="'tot'+day" class="px-1 py-1 text-center font-mono text-[11px]">{{ colDayTotal(day) ? colDayTotal(day).toLocaleString('id-ID') : '-' }}</td>
                        <td v-for="wIdx in 5" :key="'tw'+wIdx" class="px-1 py-1 text-center font-mono bg-gray-200 dark:bg-gray-800">{{ colWeekTotal(wIdx-1) ? colWeekTotal(wIdx-1).toLocaleString('id-ID') : '-' }}</td>
                        <td class="px-1 py-1 text-center font-mono bg-indigo-100 dark:bg-indigo-900/40">{{ grandPlan ? grandPlan.toLocaleString('id-ID') : '-' }}</td>
                        <td class="px-1 py-1 text-center font-mono bg-emerald-100 dark:bg-emerald-900/40">{{ grandAdj ? grandAdj.toLocaleString('id-ID') : '-' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <p class="text-[10px] text-gray-500 dark:text-gray-400">W1=1-7 · W2=8-14 · W3=15-21 · W4=22-28 · W5=29-{{ daysInMonth }} · GAP (Adj-Plan)= {{ (grandAdj - grandPlan).toLocaleString('id-ID') }}</p>

        <!-- Cell Modal -->
        <CellModal
            v-if="showCellModal"
            :item="selectedItem"
            :line="props.lines.find(l => l.id === selectedItem?.line_id) ?? props.lines[0]"
            :shift="selectedItem?.shift ?? 'shift1'"
            :day="selectedItem ? parseInt(selectedItem.tanggal.split('-')[2]) : props.daysInMonth"
            :lines="lines"
            :workCenters="workCenters"
            :monthYear="props.plan.month_year"
            @update-item="handleUpdateItem"
            @close="showCellModal = false"
        />
    </div>
</template>