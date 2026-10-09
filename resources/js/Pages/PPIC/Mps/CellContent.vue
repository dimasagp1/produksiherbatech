<script setup lang="ts">
import { computed } from 'vue';

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

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
    default_work_center_id: number | null;
    effective_ct_seconds: number | null;
    can_run_work_centers: string[] | null;
}

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

const props = defineProps<{
    day: number;
    line: Line;
    shift: string;
    item: MpsItem | null;
    workCenters: WorkCenter[];
}>();

const emit = defineEmits<{
    (e: 'click', day: number, line: Line, shift: string): void;
}>();

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

const gapColor = computed(() => {
    if (!props.item || props.item.adjusted_qty === null) return 'text-gray-400';
    const gap = props.item.adjusted_qty - props.item.target_qty;
    if (gap > 0) return 'text-emerald-600 dark:text-emerald-400';
    if (gap < 0) return 'text-rose-600 dark:text-rose-400';
    return 'text-gray-600';
});

function handleClick() {
    emit('click', props.day, props.line, props.shift);
}

function formatGap(gap: number | null): string {
    if (gap === null) return '';
    const sign = gap >= 0 ? '+' : '';
    return `${sign}${gap.toLocaleString('id-ID')}`;
}
</script>

<template>
    <div
        class="relative h-16 border-r border-gray-100 dark:border-gray-700 last:border-r-0"
        :class="[shiftColor[shift]]"
        @click="handleClick"
        style="cursor: pointer; min-height: 64px;"
    >
        <div class="absolute inset-0 border border-dashed border-gray-200 dark:border-gray-600 rounded pointer-events-none" />
        
        <div class="relative z-10 h-full flex flex-col items-center justify-center gap-0.5 p-1">
            <!-- Shift Label -->
            <span class="text-[8px] font-bold" :class="shiftColor[shift]">
                {{ shiftLabel[shift] }}
            </span>
            
            <!-- Content -->
            <div v-if="item?.cleaning" class="flex flex-col items-center gap-0.5 text-center">
                <span class="text-lg">🧹</span>
                <span class="text-[9px] font-semibold text-amber-700 dark:text-amber-300">CLEANING</span>
            </div>
            
            <div v-else-if="item" class="flex flex-col items-center gap-0.5 text-center">
                <span v-if="item.workCenter" class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded text-[9px] font-bold" :class="typeColor[item.workCenter.type]">
                    {{ typeIcon[item.workCenter.type] }}
                    {{ item.workCenter.code }}
                </span>
                <span v-else class="text-[9px] text-gray-500">No WC</span>
                
                <span class="text-[10px] font-mono font-semibold text-gray-900 dark:text-gray-100">
                    {{ item.target_qty.toLocaleString('id-ID') }}
                </span>
                
                <span class="text-[8px] text-gray-500">
                    MP: {{ item.mp_count }}
                </span>
                
                <span v-if="item.adjusted_qty !== null" class="text-[8px] font-semibold" :class="gapColor">
                    {{ item.adjusted_qty - item.target_qty >= 0 ? '+' : '' }}{{ item.adjusted_qty - item.target_qty }}
                </span>
            </div>
            
            <div v-else class="text-center text-gray-400">
                <span class="text-[10px]">—</span>
                <span class="text-[8px]">Kosong</span>
            </div>
        </div>
    </div>
</template>