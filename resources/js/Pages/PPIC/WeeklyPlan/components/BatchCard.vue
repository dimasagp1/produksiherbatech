<script setup lang="ts">
import { computed } from 'vue';

interface Line {
    id: number;
    kode_line: string;
    nama_line: string;
}
interface Produk {
    id: number;
    nama_produk: string;
    kode_produk?: string;
}

export interface BoardPlan {
    id: number;
    produk_id: number;
    line_id?: number | null;
    proses: string;
    batch_number: string;
    odoo_mo_id?: number | null;
    mo_status?: string;
    target_output?: number;
    mp_count?: number;
    multiplier?: number;
    packing_hold?: boolean;
    tanggal: string;
    status: string;
    produk?: Produk;
    line?: Line | null;
}

const props = defineProps<{
    plan: BoardPlan;
    multiplier: number;
}>();

const emit = defineEmits<{
    (e: 'activate', id: number): void;
    (e: 'toggle-hold', plan: BoardPlan): void;
    (e: 'edit', plan: BoardPlan): void;
}>();

const prosesColor: Record<string, string> = {
    mixing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    filling:
        'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    packing:
        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
};

const statusColor: Record<string, string> = {
    draft: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    aktif: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    selesai: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
};

const moColor: Record<string, string> = {
    pending: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    confirmed: 'bg-sky-100 text-sky-800 dark:bg-sky-900 dark:text-sky-200',
    in_progress:
        'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200',
    done: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const targetDisplay = computed(() => {
    const mp = props.plan.mp_count ?? 0;
    const mult = props.plan.multiplier || props.multiplier;
    if (props.plan.target_output && props.plan.target_output > 0)
        return props.plan.target_output;
    return mp * mult;
});
</script>

<template>
    <div
        class="rounded-lg border border-gray-200 bg-white p-2.5 shadow-sm dark:border-gray-600 dark:bg-gray-700/60"
    >
        <div class="flex items-center justify-between gap-1">
            <span
                :class="[
                    'rounded px-1.5 py-0.5 text-[10px] font-bold capitalize',
                    prosesColor[plan.proses],
                ]"
                >{{ plan.proses }}</span
            >
            <span
                :class="[
                    'rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase',
                    statusColor[plan.status] ?? 'bg-gray-100 text-gray-800',
                ]"
                >{{ plan.status }}</span
            >
        </div>
        <p class="mt-1.5 text-xs font-bold text-gray-900 dark:text-gray-100">
            {{ plan.produk?.nama_produk ?? '-' }}
        </p>
        <p class="font-mono text-[11px] text-gray-500 dark:text-gray-400">
            {{ plan.batch_number }}
        </p>
        <p class="text-[10px] text-gray-500 dark:text-gray-400">
            {{ plan.line?.nama_line ?? 'Line belum diatur' }}
        </p>

        <div class="mt-1.5 flex flex-wrap items-center gap-1">
            <span
                :class="[
                    'rounded px-1.5 py-0.5 text-[9px] font-semibold uppercase',
                    moColor[plan.mo_status ?? 'pending'] ??
                        'bg-gray-100 text-gray-700',
                ]"
            >
                MO: {{ plan.mo_status ?? 'pending' }}
            </span>
            <span
                v-if="plan.packing_hold"
                class="rounded bg-amber-100 px-1.5 py-0.5 text-[9px] font-bold uppercase text-amber-800 dark:bg-amber-900 dark:text-amber-200"
                >Hold</span
            >
        </div>

        <p class="mt-1.5 text-[11px] text-gray-600 dark:text-gray-300">
            Target:
            <strong class="font-mono">{{
                Number(targetDisplay).toLocaleString('id-ID')
            }}</strong>
            <span v-if="(plan.mp_count ?? 0) > 0" class="text-gray-400">
                ({{ plan.mp_count }} MP ×
                {{ plan.multiplier || multiplier }})</span
            >
        </p>

        <div
            class="mt-2 flex flex-wrap items-center gap-2 border-t border-gray-200/60 pt-1.5 dark:border-gray-600/60"
        >
            <button
                class="text-[11px] font-semibold text-indigo-600 hover:underline dark:text-indigo-400"
                @click="emit('edit', plan)"
            >
                Edit
            </button>
            <button
                v-if="plan.status === 'draft'"
                class="text-[11px] font-semibold text-green-600 hover:underline dark:text-green-400"
                @click="emit('activate', plan.id)"
            >
                Aktifkan
            </button>
            <button
                v-if="plan.status === 'aktif'"
                class="text-[11px] font-semibold"
                :class="
                    plan.packing_hold
                        ? 'text-amber-600 hover:underline dark:text-amber-400'
                        : 'text-indigo-600 hover:underline dark:text-indigo-400'
                "
                @click="emit('toggle-hold', plan)"
            >
                {{ plan.packing_hold ? 'Lepas Hold' : 'Hold Packing' }}
            </button>
        </div>
    </div>
</template>
