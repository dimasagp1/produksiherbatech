<script setup lang="ts">
import { computed } from 'vue';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: 'mixing' | 'filling' | 'secondary';
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    is_active: boolean;
}

interface Props {
    modelValue: number | string | null;
    workCenters: WorkCenter[];
    type?: 'mixing' | 'filling' | 'secondary';
    showCapacity?: boolean;
    disabled?: boolean;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:modelValue': [value: number | string | null];
}>();

const filteredWorkCenters = computed(() => {
    if (!props.type) return props.workCenters.filter(wc => wc.is_active);
    return props.workCenters.filter(wc => wc.is_active && wc.type === props.type);
});

const typeLabels: Record<string, string> = {
    mixing: 'Mixing',
    filling: 'Filling',
    secondary: 'Secondary (Packing/Labeling)',
};

const typeColors: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300',
    filling: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/60 dark:text-emerald-300',
    secondary: 'bg-purple-100 text-purple-800 dark:bg-purple-900/60 dark:text-purple-300',
};
</script>

<template>
    <div>
        <select
            v-model="modelValue"
            @change="$emit('update:modelValue', $event.target.value)"
            :disabled="disabled"
            class="w-full rounded-xl border-slate-300 text-xs text-slate-900 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-900 dark:text-slate-100"
            :class="{ 'opacity-50 cursor-not-allowed': disabled }"
        >
            <option :value="''" disabled>— Pilih Work Center —</option>
            <optgroup
                v-for="type in ['mixing', 'filling', 'secondary']"
                :key="type"
                :label="typeLabels[type]"
            >
                <option
                    v-for="wc in filteredWorkCenters.filter(w => w.type === type)"
                    :key="wc.id"
                    :value="wc.id"
                >
                    {{ wc.code }} - {{ wc.name }}
                    <span v-if="showCapacity" class="ml-2">
                        (CT: {{ wc.standard_ct_seconds }}s, MP: {{ wc.fit_mp }}, {{ wc.shift_hours }}j)
                    </span>
                </option>
            </optgroup>
        </select>

        <div v-if="modelValue && showCapacity" class="mt-2 p-3 rounded-lg bg-slate-50 dark:bg-slate-800/50">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span
                    class="px-2 py-0.5 rounded font-semibold"
                    :class="typeColors[selectedWorkCenter?.type ?? '']"
                >
                    {{ typeLabels[selectedWorkCenter?.type ?? ''] }}
                </span>
                <span class="font-mono text-slate-600 dark:text-slate-300">
                    CT: {{ selectedWorkCenter?.standard_ct_seconds }}s
                </span>
                <span class="font-mono text-slate-600 dark:text-slate-300">
                    Output/menit: {{ selectedWorkCenter?.standard_ct_seconds > 0 ? (60 / selectedWorkCenter.standard_ct_seconds).toFixed(1) : '-' }}
                </span>
                <span class="font-mono text-slate-600 dark:text-slate-300">
                    Shift: {{ selectedWorkCenter?.shift_hours }} jam
                </span>
                <span class="font-mono text-slate-600 dark:text-slate-300">
                    MP Standar: {{ selectedWorkCenter?.fit_mp }} orang
                </span>
                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">
                    Target/Shift: {{ calculateTarget }} pcs
                </span>
            </div>
        </div>
    </template>

<script setup lang="ts">
import { computed } from 'vue';

const selectedWorkCenter = computed(() => {
    if (!props.modelValue) return null;
    return props.workCenters.find(wc => wc.id == props.modelValue) ?? null;
});

const calculateTarget = computed(() => {
    const wc = selectedWorkCenter.value;
    if (!wc || wc.standard_ct_seconds <= 0 || wc.fit_mp <= 0) return 0;
    const outputPerMinute = 60 / wc.standard_ct_seconds;
    const minutesPerShift = wc.shift_hours * 60;
    return Math.round(outputPerMinute * minutesPerShift * wc.fit_mp);
});
</script>