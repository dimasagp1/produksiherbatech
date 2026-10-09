<script setup lang="ts">
import { computed } from 'vue';
import BatchCard, { type BoardPlan } from './components/BatchCard.vue';

interface WorkCenter {
    id: number;
    code: string;
    name: string;
    type: 'mixing' | 'filling' | 'secondary';
    standard_ct_seconds: number;
    fit_mp: number;
    shift_hours: number;
    saturday_shift_hours: number;
    is_active: boolean;
}

const props = withDefaults(
    defineProps<{
        plans: BoardPlan[];
        multiplier: number;
        startDate?: Date;
        workCenters?: WorkCenter[];
    }>(),
    {
        startDate: () => new Date(),
    },
);

const emit = defineEmits<{
    (e: 'activate', id: number): void;
    (e: 'toggle-hold', plan: BoardPlan): void;
    (e: 'edit', plan: BoardPlan): void;
}>();

const boardDays = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat'];

function getMonday(d: Date): Date {
    const date = new Date(d);
    const day = date.getDay();
    const diff = date.getDate() - day + (day === 0 ? -6 : 1);
    date.setDate(diff);
    date.setHours(0, 0, 0, 0);
    return date;
}

function formatDate(d: Date): string {
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function addDays(d: Date, n: number): Date {
    const result = new Date(d);
    result.setDate(result.getDate() + n);
    return result;
}

const weekDates = computed(() => {
    const start = getMonday(props.startDate);
    return boardDays.map((day, i) => ({
        day,
        date: formatDate(addDays(start, i)),
        dateLabel: addDays(start, i).toLocaleDateString('id-ID', {
            day: 'numeric',
            month: 'short',
        }),
    }));
});

const plansByDate = computed(() => {
    const map: Record<string, BoardPlan[]> = {};
    for (const d of weekDates.value) {
        map[d.date] = props.plans
            .filter(
                (p) =>
                    p.tanggal === d.date &&
                    p.mo_status !== 'cancelled' &&
                    p.status !== 'selesai',
            )
            .sort((a, b) =>
                a.proses === b.proses
                    ? 0
                    : a.proses === 'mixing'
                      ? -1
                      : b.proses === 'mixing'
                        ? 1
                        : a.proses === 'filling'
                          ? -1
                          : 1,
            );
    }
    return map;
});
</script>

<template>
    <div
        class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5"
    >
        <div
            v-for="wd in weekDates"
            :key="wd.date"
            class="flex flex-col rounded-xl border border-gray-200 bg-gray-50/60 p-3 dark:border-gray-700 dark:bg-gray-800/50"
        >
            <div
                class="mb-2.5 flex items-center justify-between border-b border-gray-200 pb-2 dark:border-gray-700"
            >
                <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                    {{ wd.day }}
                </h4>
                <span
                    class="rounded bg-gray-200 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                    >{{ wd.dateLabel }}</span
                >
            </div>
            <div class="min-h-[80px] flex-1 space-y-2">
                <p
                    v-if="(plansByDate[wd.date] ?? []).length === 0"
                    class="py-6 text-center text-xs text-gray-400 dark:text-gray-500"
                >
                    Tidak ada batch
                </p>
                <BatchCard
                    v-for="plan in plansByDate[wd.date] ?? []"
                    :key="plan.id"
                    :plan="plan"
                    :multiplier="multiplier"
                    :workCenters="workCenters"
                    @activate="emit('activate', $event)"
                    @toggle-hold="emit('toggle-hold', $event)"
                    @edit="emit('edit', $event)"
                />
            </div>
        </div>
    </div>
</template>
